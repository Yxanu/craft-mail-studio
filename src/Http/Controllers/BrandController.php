<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Models\BrandProfile;
use Yxanu\MailStudio\Services\BrandProfileRepository;

readonly class BrandController
{
    use AuthorizesMailStudio;

    public function __construct(private BrandProfileRepository $brands) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        return response()->json([
            'brand' => $this->brands->serialize($this->brands->create($data['name'])),
        ], 201);
    }

    public function update(Request $request, BrandProfile $brand): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO) || $brand->is_default, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'config' => ['required', 'array'],
        ]);

        return response()->json([
            'brand' => $this->brands->serialize($this->brands->update($brand, $data['config'], $data['name'])),
        ]);
    }

    public function destroy(BrandProfile $brand): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);
        abort_if($brand->is_default, 422, 'The default brand profile cannot be deleted.');
        $this->brands->delete($brand);

        return response()->json(status: 204);
    }
}
