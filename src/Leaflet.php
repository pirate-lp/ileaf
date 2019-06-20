<?php

namespace IAtelier\ILeaf;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

use ParsedownExtra;

use Michelf\Markdown as Markdown;
use Michelf\MarkdownExtra as MarkdownExtra;

use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;

trait Leaflet {
	
	public $menu;
	
	public function isLeaflet()
	{
		$path = rtrim($this->uri, '/') . ".md";
		if ( $this->disk->exists($path) )
		{
			
			$this->type = "leaflet";
			$this->path = $path;
			$this->initiateLeaflet();
			
			$this->exists = true;
		}
	}
	public function initiateLeaflet()
	{
		$uri_parts = explode('/', $this->uri);
		$this->metas['slug'] = end($uri_parts);
		$this->propagateMetas();
		$this->constructBase();
		$this->menu = $this->constructMenu($uri_parts[0]);
	}
	public function propagateMetas()
	{
		$this->metas = array_merge($this->metas, $this->retriveMetas($this->path));
	}
	
	/// EXTRA
	private function setViewsPath($path)
	{
		config(['theme.domain' => $this->domain . '::']);
		config(['theme.host' => request()->url()]);
		$title = config('theme.titles.'. $this->domain);
		config(['theme.title' => $title]);
		$this->views_path = $path;
	}
	
	private function setDomain() {
		$domains = config('iatelier-ileaf.domain');
		if ( count($domains) > 0 )
		{
			$this->host = $_SERVER['HTTP_HOST'];
			
			if ( in_array($this->host, $domains) )
			{
				preg_match('/([^.]+)\.[^.]+$/', $this->host, $preg_results);
				$this->domain = $preg_results[1];
				$this->disk = Storage::disk($this->domain);
				
				$this->setViewsPath($this->domain);
				return true;
			}
		}
		return false;
	}
	
	
	public function retriveMetas($file)
	{
		$metas = false;
		$rawContent = $this->disk->get($file);
		$pattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
	 . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
		if (preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[3]))
		{
			$metas = Yaml::parse($rawMetaMatches[3]);
			$metas = ($metas !== null) ? array_change_key_case($metas, CASE_LOWER) : array();
		}
		return $metas;
	}
	
	public function constructBase()
	{
		$uri_parts = explode('/', $this->uri);
		
		$base = $uri_parts[0] . '/index.md';
		if ( $this->disk->exists($base) )
		{
			$this->base = $this->retriveMetas($base);
			$this->base['slug'] = $uri_parts[0];
		}
		
		$base = $uri_parts[0] . '/index.html';
		if ( $this->disk->exists($base) )
		{
			$this->base = $this->retriveMetas($base);
			$this->base['slug'] = $uri_parts[0];
		}
	}
	
	public function content() {
		
		$leaf = $this->render($this->uri);
		$base = $this->base;
		return 	response()->json($leaf);
	}
	private function putView($leaf)
	{
		if ( array_key_exists('style', $leaf) )
		{
			$view = $this->views_path . '.' . $leaf['style'];
			if ( view()->exists($view) )
			{
				return $view;
			}
		} 
		elseif ( $this->views_path )
		{
			$view = $this->views_path . ".leaf";
			if ( view()->exists($view) )
			{
				return $view;
			}
		}
		
		$view = "ileaf::leaf";
		return $view;
	}
	
	public function checkStatus($leaf)
	{
		if (array_key_exists('status', $leaf))
		{
			if ($leaf['status'] == 'protected')
			{
// 				$router->aliasMiddleware('auth.user', \Path\To\Your\Middleware\custom_auth::class);
				if (!Auth::check()) {
					return abort(403, 'Unauthorized action.');
				}
			}
		}
		
	}
	
	public function leafReturn($view, $function)
	{
		if ( !request()->wantsJson() )
		{
			return response()->view($view, $function);
		}
		else
		{
			return response()->json($function);
		}
	}
	
	public function constructMenu($base) {
		$directories = [];
		foreach ( $this->disk->directories($base) as $sub )
		{
			$directories[$sub] = $this->disk->directories($sub);
		}
		return $directories;
	}
	
	public function retriveChildrenMetas()
	{
		$uri = preg_replace('/index.md$/', '', $this->uri);
		$uri = preg_replace('/index.md$/', '', $uri);
		$return = array();
		foreach ($this->disk->files($uri) as $file)
		{
			if ($file != ($this->uri) )
			{
				$metas = $this->retriveMetas($file);
				if ($metas != false)
				{
					$return[$file] = $metas;
				}
			}
		}
		return $return;
	}
	public function display() {
		$leaf = $this->render($this->path);
		$base = $this->base;
		$menu = $this->menu;
		
		$uri_parts = explode('/', $this->path);
		if ( (end($uri_parts) == "index.md") || (end($uri_parts) == "index.html") || $this->disk->exists($this->uri ) )
		{
			$leaf['children'] = $this->retriveChildrenMetas();
		}
		
		$view = $this->putView($leaf);
		$domain = ($this->domain) ? $this->domain : null;
		
		return 	$this->leafReturn($view, compact('leaf', 'base', 'menu', 'domain'), '200');
	}
	
	public function render($file)
	{
		$leaf = $this->retriveMetas($file);
		
		$this->checkStatus($leaf);
		
		$rawContent = $this->disk->get($file);
		$leaf['slug'] = $this->slug;
		
		$metaHeaderPattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
		. "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
		$content = preg_replace($metaHeaderPattern, '', $rawContent, 1);
		
		$content_css = $this->cssStandards($content);
		
// 		$extra = new ParsedownExtra();
// 		$leaf['content'] = $extra->text($content_css);

		$extra = new MarkdownExtra;
// 		$parser->fn_id_prefix = "post22-";
		
// 		$my_html = $parser->transform($my_text);
		$leaf['content'] = $extra->transform($content_css);
		
		return $leaf;
	}
	// CSS Shortkeys
	public function cssStandards($content)
	{
		if ( !empty(config('iatelier.short_keys')) )
		{
			$cssStandards = config('iatelier.short_keys');
			$keys = $values = array();
			foreach ($cssStandards as $key => $value) {
				$keys[] = '%' . $key . '%';
				$values[] = $value;
			}
			return str_replace($keys, $values, $content);
		}
		return $content;
	}
}