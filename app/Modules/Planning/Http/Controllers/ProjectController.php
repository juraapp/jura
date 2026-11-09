<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Http\Requests\Projects\StoreProjectRequest;
use App\Modules\Planning\Http\Requests\Projects\UpdateProjectRequest;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Services\ProjectManager;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request, ProjectManager $projects): RedirectResponse
    {
        $projects->save($request->user(), $request->validated());

        return back()->with('success', __('wishlist.project_saved'));
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectManager $projects): RedirectResponse
    {
        $projects->save($request->user(), $request->validated(), $project);

        return back()->with('success', __('wishlist.project_saved'));
    }

    public function destroy(Request $request, Project $project, ProjectManager $projects): RedirectResponse
    {
        $this->authorize('delete', $project);

        $projects->delete($project, $request->boolean('delete_bucket'));

        return back()->with('success', __('wishlist.project_deleted'));
    }
}
