<?php

namespace IAtelier\ILeaf;

use Illuminate\Support\Facades\View as View;
use Illuminate\Support\Facades\Response as Response;

use GuzzleHttp;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Facades\Route;

use IAtelier\IBA\Console\ModelMakeCommand as ModelMakeCommand;
use IAtelier\IBA\Console\MigrateMakeCommand as MigrateMakeCommand;
use IAtelier\IBA\Console\ControllerMakeCommand as ControllerMakeCommand;
use IAtelier\IBA\Console\BookMakeCommand;

use Illuminate\Routing\Router;

class ILeafServiceProvider extends Provider
{
	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
        	__DIR__.'/config/iatelier-ileaf.php' => config_path('iatelier-ileaf.php'),
		]);
		
		$this->loadViewsFrom(__DIR__.'/resources/views', 'ileaf');
		
		$this->publishes([
				__DIR__.'/public' => public_path('iatelier/ileaf'),
			], 'public');
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{        
        if (!Response::hasMacro('asset'))
        {
	        $this->registerResponseMacro();
        }
		$this->app->singleton('Illuminate\Contracts\Debug\ExceptionHandler','IAtelier\ILeaf\Exceptions\Handler');
	}
	
	public function registerResponseMacro()
	{
		Response::macro('asset', function ($asset_name, $uri, $disk = "ibook")
		{
			if ( substr($asset_name, -4) === '.mp4' )
			{
				$path = Storage::disk($disk)->getAdapter()->getPathPrefix();
				$file = $path . $uri;
				$mime = 'video/mp4';
				$size = filesize($file);
				$length = $size;
				$start = 0;
				$end = $size - 1;
		
				header(sprintf('Content-type: %s', $mime));
				header('Accept-Ranges: bytes');
				
				if(isset($_SERVER['HTTP_RANGE']))
				{
					$c_start = $start;
					$c_end = $end;
		
					list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		
					if(strpos($range, ',') !== false)
					{
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
		
						exit;
					}
		
					if($range == '-')
					{
							$c_start = $size - substr($range, 1);
					}
					else
					{
							$range	= explode('-', $range);
							$c_start = $range[0];
							$c_end	 = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
					}
		
					$c_end = ($c_end > $end) ? $end : $c_end;
		
					if($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size)
					{
							header('HTTP/1.1 416 Requested Range Not Satisfiable');
							header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
		
							exit;
					}
		
					header('HTTP/1.1 206 Partial Content');
		
					$start = $c_start;
					$end = $c_end;
					$length = $end - $start + 1;
				}
		
				header("Content-Range: bytes $start-$end/$size");
				header(sprintf('Content-Length: %d', $length));
		
				$fh = fopen($file, 'rb');
				$buffer = 1024 * 8;
		
				fseek($fh, $start);
		
				while(true)
				{
					if(ftell($fh) >= $end) break;
		
					set_time_limit(0);
		
					echo fread($fh, $buffer);
		
					flush();
				}


			}
			elseif (substr($asset_name, -5) === '.html')
			{
				$uri = preg_replace('/.html$/', '', $uri)  . '/';
				return redirect($uri);
			}
			elseif (substr($asset_name, -3) === '.md')
			{
				$uri = preg_replace('/.md$/', '', $uri) . '/';
				return redirect($uri);
			}
			elseif (
				(substr($asset_name, -4) === '.jpg')
				(substr($asset_name, -5) === '.jpeg')
				(substr($asset_name, -4) === '.png')
				(substr($asset_name, -4) === '.ttf')
				(substr($asset_name, -4) === '.gif')
				(substr($asset_name, -5) === '.svg')
			)
			{
				$content = Storage::disk($disk)->get($uri);
// 				dd($uri);
				return response($content, 200)->withHeaders([
					'Content-Type' => 'image',
					'X-Header-One' => 'Header Value',
					'X-Header-Two' => 'Header Value',
				]);
			}
			else
			{
				return response()->download(
					Storage::disk($disk)->get($uri),
					$asset_name
				);
			}
		});
	}
}
