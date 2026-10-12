<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Http\Requests\Wishlist\StoreProjectRequest;
use App\Modules\Planning\Http\Requests\Wishlist\UpdateProjectRequest;
use App\Modules\Planning\Models\Project;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = new Project($request->validated());
        $project->user_id = $request->user()->id;
        $project->save();

        return back()->with('success', __('wishlist.project_saved'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return back()->with('success', __('wishlist.project_saved'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return back()->with('success', __('wishlist.project_deleted'));
    }
}
