<?php

namespace Omnibank\Model;

/**
 * What became of a transfer: its id at the provider (or the message id of
 * the file), the provider's status for it, and - when the transfer is a file
 * to hand the bank (a pain.001) - the file's content and name.
 */
final readonly class TransferResult
{
    public function __construct(
        public string $id,
        /** "generated" (a file to upload), or the provider's own: "pending", "executed", "rejected"... */
        public string $status,
        public ?string $file = null,
        public ?string $fileName = null,
    ) {
    }
}
