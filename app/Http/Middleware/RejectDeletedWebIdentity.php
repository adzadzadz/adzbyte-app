<?php

namespace App\Http\Middleware;

/** Web sessions must end before Filament's panel-access check can abort. */
final class RejectDeletedWebIdentity extends RejectDeletedIdentity {}
