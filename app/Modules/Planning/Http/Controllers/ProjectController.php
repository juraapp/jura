<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\ProjectKind;
use App\Modules\Planning\Http\Requests\Projects\StoreProjectRequest;
use App\Modules\Planning\Http\Requests\Projects\UpdateProjectRequest;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Services\ProjectManager;
use App\Modules\Planning\Services\ProjectOverview;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /** How far ahead the planner looks for a project's completion date. */
    private const MONTHS = 24;

    public function index(ProjectOverview $overview): Response
    {
        $this->authorize('viewAny', Project::class);

        return Inertia::render('Projects/Index', [
            'projects' => $overview->index(Auth::user(), months: self::MONTHS),
        ] + $this->formOptions());
    }

    public function show(Project $project, ProjectOverview $overview): Response
    {
        $this->authorize('view', $project);

        $entry = collect($overview->index(Auth::user(), months: self::MONTHS, withItems: true))->firstWhere('id', $project->id);

        return Inertia::render('Projects/Show', [
            'project' => $entry,
            'projects' => $overview->index(Auth::user(), months: self::MONTHS),
        ] + $this->formOptions());
    }

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

        // A project's own page no longer exists: go to the list instead of back to it.
        $previous = parse_url(url()->previous(), PHP_URL_PATH) ?: '';
        $redirect = str_starts_with($previous, route('projects.index', absolute: false).'/')
            ? redirect()->route('projects.index')
            : back();

        return $redirect->with('success', __('wishlist.project_deleted'));
    }

    /**
     * What the project form needs: the kinds, the user's currencies and their savings buckets.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $currencies = Auth::user()->accounts()->pluck('currency')->unique()->sort()->values();

        return [
            'projectKinds' => collect(ProjectKind::cases())->map(fn (ProjectKind $kind) => ['value' => $kind->value, 'label' => $kind->label()])->values(),
            'currencies' => $currencies->isEmpty() ? collect([Auth::user()->currency_default]) : $currencies,
            'buckets' => Bucket::inPriorityOrder()->where('kind', BucketKind::Savings->value)->get(['id', 'name', 'currency']),
            'view' => ForecastView::default()->value,
            'months' => self::MONTHS,
        ];
    }
}
