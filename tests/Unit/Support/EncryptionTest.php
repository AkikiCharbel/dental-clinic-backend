<?php

declare(strict_types=1);

use App\Support\Encryption;

describe('Encryption Helper', function (): void {
    describe('encrypt()', function (): void {
        it('encrypts a plain text value', function (): void {
            $plainText = 'sensitive-data-123';

            $encrypted = Encryption::encrypt($plainText);

            expect($encrypted)->not->toBe($plainText);
            expect($encrypted)->toStartWith('eyJ'); // Base64 JSON prefix
        });

        it('returns null for null input', function (): void {
            expect(Encryption::encrypt(null))->toBeNull();
        });

        it('returns empty string for empty string input', function (): void {
            expect(Encryption::encrypt(''))->toBe('');
        });

        it('does not double-encrypt already encrypted values', function (): void {
            $plainText = 'my-secret';
            $encrypted = Encryption::encrypt($plainText);
            $doubleEncrypted = Encryption::encrypt($encrypted);

            expect($doubleEncrypted)->toBe($encrypted);
        });
    });

    describe('decrypt()', function (): void {
        it('decrypts an encrypted value', function (): void {
            $plainText = 'sensitive-data-456';
            $encrypted = Encryption::encrypt($plainText);

            $decrypted = Encryption::decrypt($encrypted);

            expect($decrypted)->toBe($plainText);
        });

        it('returns null for null input', function (): void {
            expect(Encryption::decrypt(null))->toBeNull();
        });

        it('returns empty string for empty string input', function (): void {
            expect(Encryption::decrypt(''))->toBe('');
        });

        it('returns plain text if not encrypted', function (): void {
            $plainText = 'not-encrypted';

            expect(Encryption::decrypt($plainText))->toBe($plainText);
        });

        it('returns null for invalid encrypted data', function (): void {
            // Valid-looking but corrupted encryption
            $invalid = 'eyJpdiI6ImludmFsaWQiLCJ2YWx1ZSI6ImludmFsaWQiLCJtYWMiOiJpbnZhbGlkIn0=';

            expect(Encryption::decrypt($invalid))->toBeNull();
        });
    });

    describe('isEncrypted()', function (): void {
        it('returns true for encrypted values', function (): void {
            $encrypted = Encryption::encrypt('test-value');

            expect(Encryption::isEncrypted($encrypted))->toBeTrue();
        });

        it('returns false for plain text', function (): void {
            expect(Encryption::isEncrypted('plain-text'))->toBeFalse();
        });

        it('returns false for null', function (): void {
            expect(Encryption::isEncrypted(null))->toBeFalse();
        });

        it('returns false for empty string', function (): void {
            expect(Encryption::isEncrypted(''))->toBeFalse();
        });

        it('returns false for non-JSON base64', function (): void {
            // Base64 that doesn't decode to valid encryption JSON
            expect(Encryption::isEncrypted('dGVzdA=='))->toBeFalse();
        });
    });

    describe('searchHash()', function (): void {
        it('generates deterministic hash', function (): void {
            $value = 'test@example.com';

            $hash1 = Encryption::searchHash($value);
            $hash2 = Encryption::searchHash($value);

            expect($hash1)->toBe($hash2);
        });

        it('generates different hashes for different values', function (): void {
            $hash1 = Encryption::searchHash('value1');
            $hash2 = Encryption::searchHash('value2');

            expect($hash1)->not->toBe($hash2);
        });

        it('returns null for null input', function (): void {
            expect(Encryption::searchHash(null))->toBeNull();
        });

        it('returns null for empty string', function (): void {
            expect(Encryption::searchHash(''))->toBeNull();
        });

        it('normalizes case for consistent hashing', function (): void {
            $hash1 = Encryption::searchHash('Test@Example.com');
            $hash2 = Encryption::searchHash('test@example.com');

            expect($hash1)->toBe($hash2);
        });

        it('trims whitespace for consistent hashing', function (): void {
            $hash1 = Encryption::searchHash('  test@example.com  ');
            $hash2 = Encryption::searchHash('test@example.com');

            expect($hash1)->toBe($hash2);
        });

        it('generates 64 character SHA256 hash', function (): void {
            $hash = Encryption::searchHash('test-value');

            expect(strlen($hash))->toBe(64);
            expect(ctype_xdigit($hash))->toBeTrue();
        });
    });

    describe('mask()', function (): void {
        it('masks middle characters of a string', function (): void {
            $result = Encryption::mask('1234567890', 2, 4);

            expect($result)->toBe('12****7890');
        });

        it('masks with default parameters (last 4 visible)', function (): void {
            $result = Encryption::mask('123-45-6789');

            expect($result)->toBe('*******6789');
        });

        it('returns null for null input', function (): void {
            expect(Encryption::mask(null))->toBeNull();
        });

        it('returns empty string for empty input', function (): void {
            expect(Encryption::mask(''))->toBe('');
        });

        it('masks entire string when shorter than visible characters', function (): void {
            $result = Encryption::mask('123', 2, 4);

            expect($result)->toBe('***');
        });

        it('uses custom mask character', function (): void {
            $result = Encryption::mask('secret', 1, 1, '#');

            expect($result)->toBe('s####t');
        });
    });

    describe('maskEmail()', function (): void {
        it('masks email local part', function (): void {
            $result = Encryption::maskEmail('john.doe@example.com');

            expect($result)->toBe('j******e@example.com');
        });

        it('returns null for null input', function (): void {
            expect(Encryption::maskEmail(null))->toBeNull();
        });

        it('returns original if no @ symbol', function (): void {
            expect(Encryption::maskEmail('invalid-email'))->toBe('invalid-email');
        });

        it('handles short local parts', function (): void {
            $result = Encryption::maskEmail('ab@example.com');

            expect($result)->toBe('ab@example.com');
        });
    });

    describe('maskPhone()', function (): void {
        it('masks phone showing last 4 digits', function (): void {
            $result = Encryption::maskPhone('+1-555-123-4567');

            expect($result)->toBe('***-***-4567');
        });

        it('returns null for null input', function (): void {
            expect(Encryption::maskPhone(null))->toBeNull();
        });

        it('handles phone without formatting', function (): void {
            $result = Encryption::maskPhone('5551234567');

            expect($result)->toBe('***-***-4567');
        });

        it('masks entirely if less than 4 digits', function (): void {
            $result = Encryption::maskPhone('123');

            expect($result)->toBe('***');
        });
    });
});
