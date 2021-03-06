<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\Auth;

class ProjectActivityController extends Controller
{

    public function index(Request $request)
    {
        $activities = ProjectActivity::where('project_id', $request->route('project_id'))->with('user')->orderBy('created_at', 'desc')->simplePaginate(100);
        return view('projects.activities', compact('activities'));
    }

    public static function save($project_id, $msg, $null_user = false)
    {
        $activity = new ProjectActivity();
        if ($null_user) {
            $user_id = null;
        } else {
            $user_id = Auth::id();
        }
        $activity->user_id = $user_id;
        $activity->project_id = $project_id;
        $activity->msg = $msg;
        $activity->save();

        return true;
    }
}
