<?php

use App\Http\Controllers\Intelligence\BriefingController;
use App\Http\Controllers\Intelligence\CountryController;
use App\Http\Controllers\Intelligence\FindController;
use App\Http\Controllers\Intelligence\HomeController;
use App\Http\Controllers\Intelligence\IndicatorController;
use App\Http\Controllers\Intelligence\IndustryController;
use App\Http\Controllers\Intelligence\NewsroomController;
use App\Http\Controllers\Intelligence\PageController;
use App\Http\Controllers\Intelligence\SourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The public library
|--------------------------------------------------------------------------
| Nobody signs in to read. These pages carry no session and no cookie, are cached whole, and reach only the intelligence
| store, never a company's data. The `public` middleware group is applied in bootstrap/app.php.
*/

Route::get('/', HomeController::class)->name('intel.home');

Route::get('/industries', [IndustryController::class, 'index'])->name('intel.industries');
Route::get('/industries/{industry:slug}', [IndustryController::class, 'show'])->name('intel.industry');

Route::get('/countries', [CountryController::class, 'index'])->name('intel.countries');
Route::get('/countries/{geography:slug}', [CountryController::class, 'show'])->name('intel.country');

Route::get('/indicators', [IndicatorController::class, 'index'])->name('intel.indicators');
Route::get('/indicators/{indicator:key}', [IndicatorController::class, 'show'])->where('indicator', '[a-z0-9._-]+')->name('intel.indicator');
Route::get('/indicators/{indicator:key}/values.csv', [IndicatorController::class, 'csv'])->where('indicator', '[a-z0-9._-]+')->name('intel.indicator.csv');

Route::get('/newsroom', [NewsroomController::class, 'index'])->name('intel.newsroom');
Route::get('/newsroom/{item:uuid}', [NewsroomController::class, 'show'])->whereUuid('item')->name('intel.item');

Route::get('/briefings', [BriefingController::class, 'index'])->name('intel.briefings');
Route::get('/briefings/{edition}', [BriefingController::class, 'latest'])->where('edition', '[a-z-]+')->name('intel.briefing.latest');
Route::get('/briefings/{edition}/{date}', [BriefingController::class, 'show'])->where(['edition' => '[a-z-]+', 'date' => '\d{4}-\d{2}-\d{2}'])->name('intel.briefing');

Route::get('/sources', SourceController::class)->name('intel.sources');
Route::get('/find', FindController::class)->middleware('throttle:public-search')->name('intel.find');
Route::get('/method', [PageController::class, 'method'])->name('intel.method');
Route::get('/plans', [PageController::class, 'plans'])->name('intel.plans');
Route::get('/ecosystem', [PageController::class, 'ecosystem'])->name('intel.ecosystem');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('intel.sitemap');
