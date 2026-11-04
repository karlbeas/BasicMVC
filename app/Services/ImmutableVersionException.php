<?php

declare(strict_types=1);

namespace App\Services;

/** Raised on any attempt to modify a published form_version (MANIFEST rule 3). */
final class ImmutableVersionException extends \DomainException {}
