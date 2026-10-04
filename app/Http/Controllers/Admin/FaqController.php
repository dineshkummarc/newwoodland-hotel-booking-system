<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Models\Faq;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::orderBy('sort_order')->get();

        return view('admin.faqs.index', compact('faqs'));
    }

    public function create()
    {
        return view('admin.faqs.create');
    }

    public function store(StoreFaqRequest $request, AuditLogService $auditLog)
    {
        $data = $request->validated();
        $data['sort_order'] = (Faq::max('sort_order') ?? 0) + 1;

        $faq = Faq::create($data);

        $auditLog->log('faq.created', 'faq', $faq->id, "Created FAQ \"{$faq->question}\".");

        Cache::forget('home.page.data');

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ created.');
    }

    public function edit(Faq $faq)
    {
        return view('admin.faqs.edit', compact('faq'));
    }

    public function update(StoreFaqRequest $request, Faq $faq, AuditLogService $auditLog)
    {
        $before = $faq->getAttributes();
        $faq->update($request->validated());

        $auditLog->logChanges('faq.updated', 'faq', $faq, "Updated FAQ \"{$faq->question}\".", $before);

        Cache::forget('home.page.data');

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated.');
    }

    public function destroy(Faq $faq, AuditLogService $auditLog)
    {
        $faq->delete();

        $auditLog->log('faq.deleted', 'faq', $faq->id, "Deleted FAQ \"{$faq->question}\".");

        Cache::forget('home.page.data');

        return back()->with('success', 'FAQ deleted.');
    }

    public function move(Faq $faq, string $direction)
    {
        $swap = $direction === 'up'
            ? Faq::where('sort_order', '<', $faq->sort_order)->orderBy('sort_order', 'desc')->first()
            : Faq::where('sort_order', '>', $faq->sort_order)->orderBy('sort_order')->first();

        if ($swap) {
            [$a, $b] = [$faq->sort_order, $swap->sort_order];
            $faq->update(['sort_order' => $b]);
            $swap->update(['sort_order' => $a]);
        }

        Cache::forget('home.page.data');

        return back();
    }
}
