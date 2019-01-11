<?php

namespace PirateLP\ILeaf;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;

use ParsedownExtra;

use Michelf\Markdown as Markdown;
use Michelf\MarkdownExtra as MarkdownExtra;

use Symfony\Component\Yaml\Yaml;

class Leaf {
	
	public $type;
    public $uri;
    public $base;
    public $path;
    
    private $disk;
    private $domain;
    private $views_path;
    
    protected $slug;
    
    function __construct($uri)
    {
	    
	    $this->setDisk('leaves');
	    
	    $this->setViewPath();
	    $this->setDomain();
	    
	    $this->path = $uri;
	    $this->uri = $this->exists($uri);
	    if ( $this->uri )
	    {
		    $uri_parts = explode('/', $uri);
			$this->constructBase();
			$this->menu = $this->constructMenu($uri_parts[0]);
	    }
	    return $this->uri;
    }
    
    private function setDisk($name)
    {
	    $this->disk = Storage::disk($name);
    }
    
    private function setViewsPath($path = null)
    {
	    $this->views_path = (!$path) ? $path . ".leaf" : null;
    }
    
    private function setDomain() {
	    $domains = config('iba-ileaf.domain');
	    if ( count($domains) > 0 )
	    {
		    $host = $_SERVER['HTTP_HOST'];
			if ( in_array($host, $domains) )
			{
				$this->domain = $host;
				$this->setDisk($host);
				$this->setViewPath($host);
				return true;
			}
	    }
		return false;
    }
    
    public function exists($uri)
	{
		$file = rtrim($uri,'/') . ".md";
		$directory = $uri . "/index.md";
		$asset = $uri;
		if ( $this->disk->exists($file) ) {
			$this->type = "file";
			return $file;
		}
		if ( $this->disk->exists($directory) ) {
			$this->type = "directory";
			return $directory;
		}
		if ( $this->disk->exists($asset) ) {
			$this->type = "asset";
			return $asset;
		}
		return false;
	}
	
	public function constructBase()
	{
		$uri_parts = explode('/', $this->uri);
		
		$base = $uri_parts[0] . '/index.md';
		if ( $this->disk->exists($base) )
		{
			$this->base = $this->retriveMetas($base, 'leaves');
			$this->base['slug'] = $uri_parts[0];
		}
		
		$base = $uri_parts[0] . '/index.html';
		if ( $this->disk->exists($base) )
		{
			$this->base = $this->retriveMetas($base, 'leaves');
			$this->base['slug'] = $uri_parts[0];
		}
	}
	
	
	public function cssStandards($content)
	{
		if ( !empty(config('iba.short_keys')) )
		{
			$cssStandards = config('iba.short_keys');
	        $keys = $values = array();
	        foreach ($cssStandards as $key => $value) {
			    $keys[] = '%' . $key . '%';
			    $values[] = $value;
	        }
	        return str_replace($keys, $values, $content);
        }
        return $content;
	}
    
    
    public function render($file, $disk = 'seed')
	{
	    $leaf = $this->retriveMetas($file, $disk);
	    $this->check_status($leaf);
	    $rawContent = Storage::disk($disk)->get($file);
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
	
	public function retriveMetas($file, $disk)
	{
		$metas = false;
		$rawContent = Storage::disk($disk)->get($file);
	    $pattern = "/^(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
	 . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
	    if (preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[3]))
	    {
	        $metas = Yaml::parse($rawMetaMatches[3]);
	        $metas = ($metas !== null) ? array_change_key_case($metas, CASE_LOWER) : array();
	    }
	    return $metas;
	}
	
	public function content() {
		
		$leaf = $this->render($this->uri, 'leaves');
		$base = $this->base;
		return 	response()->json($leaf);
	}
	
	
	public function show()
	{
		switch ($this->type) {
			case "directory":
			case "file":
				return $this->display();
				break;
			case "asset":
				$asset = $this->disk->get($this->uri);
				$asset_name = last(explode('/', $this->uri));
				return response()->asset($asset_name, $this->uri, 'leaves');
				break;
		}
	}
	
	public function display() {
		$leaf = $this->render($this->uri, 'leaves');
		$base = $this->base;
		$menu = $this->menu;
		
		$uri_parts = explode('/', $this->uri);
		if ( (end($uri_parts) == "index.md") || (end($uri_parts) == "index.html") || $this->disk->exists($this->path ) )
		{
			$leaf['children'] = $this->retriveChildrenMetas();
		}
		
		$view = $this->putView($leaf);
		
		return 	$this->leafReturn($view, compact('leaf', 'base', 'menu'), '200');
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
			$this->views_path . ".leaf";
			if ( view()->exists($view) )
			{
				return $view;
			}
		}
		$view = "ileaf::leaf";
		return $view;
	}
	
	public function check_status($leaf)
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
				$metas = $this->retriveMetas($file, 'leaves');
				if ($metas != false)
				{
					$return[$file] = $metas;
				}
			}
		}
		return $return;
	}
}