<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Family $family): View
    {
        $this->authorize('manageAccess', $family);

        return view('families.audit', ['family' => $family, 'entries' => AuditLog::where('family_id', $family->id)->latest('id')->paginate(30)]);
    }
}
