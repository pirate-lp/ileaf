<?php

namespace IAtelier\ILeaf;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

use IAtelier\ILeaf\Leaflet as Leaflet;
use IAtelier\ILeaf\Directory as Directory;

class Leaf {
	
	use Leaflet, Directory;
	
	public $exists;
	
	public $type;
	public $uri;
	public $base;
	public $path;
	public $metas = [];
	
	private $disk_name;
	private $disk;
	private $host;
	private $domain;
	private $views_path;
	
	public $slug;
	
	function __construct($uri)
	{
		$this->disk = Storage::disk('leaves');
		$this->disk_name = 'leaves';
		$this->views_path = config('iatelier-ileaf.views_path');
		$this->setDomain();
		
		$this->uri = $uri;
		
		$this->exists();
	}
	
	public function show()
	{
		switch ($this->type) {
			case "directory":
			case "leaflet":
				return $this->display();
				break;
			
			case "asset":
				$asset = $this->disk->get($this->uri);
				$asset_name = last(explode('/', $this->uri));
				return response()->asset($asset_name, $this->uri, $this->disk_name);
				break;
		}
	}
	
	public function exists()
	{
		$this->exists = false;
		$this->isAsset();
		$this->isDirectory();
		$this->isLeaflet();
	}
	public function isAsset()
	{
		if ( $this->disk->exists($this->uri) ) {
			$this->type = "asset";
			$this->path = $this->uri;
			$this->exists = true;
		}

	}
	
}