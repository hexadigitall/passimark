<?php

namespace App\Support;

use App\Exceptions\PassimarkPackageException;

/**
 * Minimal, dependency-free ZIP codec used by the Passimark package format (.psmk).
 *
 * Produces and parses standard ZIP archives (method 0 = store, method 8 = deflate via zlib),
 * so packages are interchangeable with any ZIP tool. The archive layout follows the classic
 * local-file-header / central-directory / end-of-central-directory structure.
 *
 * The server runtime may or may not have ext-zip; this codec does not depend on it.
 */
final class PsmkZip
{
    private const SIG_LOCAL = 0x04034b50;
    private const SIG_CENTRAL = 0x02014b50;
    private const SIG_EOCD = 0x06054b50;

    /** Pack a list of ["name" => "contents"] into ZIP binary strings. */
    public static function build(array $files, bool $forceStore = false): string
    {
        $out = '';
        $central = '';
        $offset = 0;

        foreach ($files as $name => $data) {
            $data = (string) $data;
            $size = strlen($data);
            $crc = crc32($data);

            if ($forceStore) {
                $method = 0;
                $payload = $data;
            } else {
                $packed = gzdeflate($data, 6);
                if ($packed !== false && strlen($packed) < $size) {
                    $method = 8;
                    $payload = $packed;
                } else {
                    $method = 0;
                    $payload = $data;
                }
            }

            $csize = strlen($payload);
            $nameBin = $name;
            $nameLen = strlen($nameBin);

            $local = pack('VvvvvvVVVvv', self::SIG_LOCAL, 20, 0x0800, $method, 0, 0, $crc, $csize, $size, $nameLen, 0) . $nameBin;
            $out .= $local . $payload;

            $central .= pack('VvvvvvvVVVvvvvvVV', self::SIG_CENTRAL, 20, 20, 0x0800, $method, 0, 0, $crc, $csize, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $nameBin;

            $offset += strlen($local) + $csize;
        }

        $cdSize = strlen($central);
        $eocd = pack('VvvvvVVv', self::SIG_EOCD, 0, 0, count($files), count($files), $cdSize, $offset, 0);

        return $out . $central . $eocd;
    }

    /** Inspect an archive without inflating content. Returns ["count", "entries":[name, method, csize, usize]]. */
    public static function inspect(string $binary): array
    {
        $eocd = self::findEocd($binary);
        $count = unpack('v', substr($binary, $eocd['offset'] + 10, 2))[1];
        $cdSize = unpack('V', substr($binary, $eocd['offset'] + 12, 4))[1];
        $cdOffset = unpack('V', substr($binary, $eocd['offset'] + 16, 4))[1];

        if ($cdOffset + $cdSize > strlen($binary)) {
            throw new PassimarkPackageException('Corrupt package archive: central directory out of bounds.');
        }

        $entries = [];
        $pos = $cdOffset;
        for ($i = 0; $i < $count; $i++) {
            if (substr($binary, $pos, 4) !== pack('V', self::SIG_CENTRAL)) {
                throw new PassimarkPackageException('Corrupt package archive: bad central-directory record.');
            }
            $method = unpack('v', substr($binary, $pos + 10, 2))[1];
            $crc = unpack('V', substr($binary, $pos + 16, 4))[1];
            $csize = unpack('V', substr($binary, $pos + 20, 4))[1];
            $usize = unpack('V', substr($binary, $pos + 24, 4))[1];
            $nameLen = unpack('v', substr($binary, $pos + 28, 2))[1];
            $extraLen = unpack('v', substr($binary, $pos + 30, 2))[1];
            $commentLen = unpack('v', substr($binary, $pos + 32, 2))[1];
            $localOffset = unpack('V', substr($binary, $pos + 42, 4))[1];

            $name = substr($binary, $pos + 46, $nameLen);
            $entries[$name] = [
                'name' => $name,
                'method' => $method,
                'crc' => $crc,
                'csize' => $csize,
                'usize' => $usize,
                'local_offset' => $localOffset,
                'extra_len' => $extraLen,
                'comment_len' => $commentLen,
            ];

            $pos += 46 + $nameLen + $extraLen + $commentLen;
        }

        return ['count' => $count, 'entries' => $entries];
    }

    /** Read a whole archive, returning ["name" => "contents"] with integrity verification. */
    public static function read(string $binary): array
    {
        $meta = self::inspect($binary);
        $out = [];

        foreach ($meta['entries'] as $e) {
            $start = $e['local_offset'];
            while (substr($binary, $start, 4) !== pack('V', self::SIG_LOCAL)) {
                $start++;
                if ($start >= strlen($binary)) {
                    throw new PassimarkPackageException('Corrupt package archive: missing local header.');
                }
            }
            $nameLen = unpack('v', substr($binary, $start + 26, 2))[1];
            $extraLen = unpack('v', substr($binary, $start + 28, 2))[1];
            $dataStart = $start + 30 + $nameLen + $extraLen;

            if (($dataStart + $e['csize']) > strlen($binary)) {
                throw new PassimarkPackageException("Corrupt package archive: data truncated for {$e['name']}.");
            }

            $raw = substr($binary, $dataStart, $e['csize']);

            if ($e['method'] === 0) {
                $content = $raw;
            } elseif ($e['method'] === 8) {
                $content = gzinflate($raw);
                if ($content === false) {
                    throw new PassimarkPackageException("Corrupt package archive: bad deflate payload for {$e['name']}.");
                }
            } else {
                throw new PassimarkPackageException("Unsupported compression method {$e['method']} for {$e['name']}.");
            }

            if (strlen($content) !== $e['usize']) {
                throw new PassimarkPackageException("Size mismatch reading {$e['name']}.");
            }
            if ((crc32($content) & 0xFFFFFFFF) !== ($e['crc'] & 0xFFFFFFFF)) {
                throw new PassimarkPackageException("Checksum mismatch reading {$e['name']}.");
            }

            $out[$e['name']] = $content;
        }

        return $out;
    }

    private static function findEocd(string $binary): array
    {
        $needle = pack('V', self::SIG_EOCD);
        $from = max(0, strlen($binary) - 22 - 65535);
        $pos = strrpos(substr($binary, $from), $needle);
        if ($pos === false) {
            throw new PassimarkPackageException('Not a valid package archive (no end-of-central-directory record).');
        }
        return ['offset' => $from + $pos];
    }
}