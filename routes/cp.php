<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yxanu\MailStudio\Http\Controllers\AppController;
use Yxanu\MailStudio\Http\Controllers\BootstrapController;
use Yxanu\MailStudio\Http\Controllers\BrandController;
use Yxanu\MailStudio\Http\Controllers\ImportExportController;
use Yxanu\MailStudio\Http\Controllers\MappingController;
use Yxanu\MailStudio\Http\Controllers\PreviewController;
use Yxanu\MailStudio\Http\Controllers\TemplateController;

Route::prefix('mail-studio')->name('mail-studio.')->group(function (): void {
    Route::get('/', AppController::class)->name('index');

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('templates/{template}/publish', [TemplateController::class, 'publish'])->name('templates.publish');
        Route::post('templates/{template}/revisions/{revision}/rollback', [TemplateController::class, 'rollback'])->name('templates.rollback');
        Route::post('templates/{template}/preview', [PreviewController::class, 'render'])->name('templates.preview');
        Route::post('templates/{template}/test', [PreviewController::class, 'send'])->name('templates.test');
        Route::put('mappings', [MappingController::class, 'store'])->name('mappings.store');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
        Route::get('export', [ImportExportController::class, 'export'])->name('export');
        Route::post('import', [ImportExportController::class, 'import'])->name('import');
    });
});
