<?php

namespace App\Services\PackageCheckout;

use RuntimeException;

// Never attach provider bodies, transport errors, authenticated URLs or headers.
final class QrphFailure extends RuntimeException {}
