1.composer create-project --prefer-dist laravel/laravel your_project_name

2.
    composer require laravel/ui
    php artisan ui bootstrap --auth
    or
    composer require laravel/breeze --dev
    php artisan breeze:install

3. php artisan migrate
    

4.npm install && npm run dev

php artisan make:controller UserController
php artisan make:middleware AdminMiddleware


#Localization

config/app.php
'locales' => ['en', 'fr', 'es'],
php artisan make:middleware Localization

//Register Middleware
'localization' => \App\Http\Middleware\Localization::class,

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (!in_array($locale, config('app.locales'))) {
            abort(404);
        }

        App::setLocale($locale);
    }
}

Route::group([
    'prefix' => '{locale}',
    'middleware' => 'localization'
], function () {

    Route::get('/blog', [BlogController::class, 'index'])
        ->name('blog.index');

    Route::get('/post/{slug}', [BlogController::class, 'show'])
        ->name('blog.show');
});

Route::get('/', function () {
    $locale = substr(request()->server('HTTP_ACCEPT_LANGUAGE'), 0, 2);
    if (!in_array($locale, config('app.locales'))) {
        $locale = 'en';
    }
    return redirect("/$locale/blog");
});
