<?php

namespace App\Domain\Files\Exceptions;

use LogicException;

/**
 * A stored file is never changed nor overwritten (C10, M05 RB-07): a new content is a new file.
 */
final class StoredFileImmutable extends LogicException
{
    public static function make(): self
    {
        return new self('Stored files are never changed: store a new file instead.');
    }

    public static function pathTaken(string $path): self
    {
        return new self("A file already exists at {$path}: stored files are never overwritten.");
    }
}
