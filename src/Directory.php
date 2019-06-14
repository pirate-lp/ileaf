<?php

namespace IAtelier\ILeaf;

use Illuminate\Support\Facades\Storage;

trait Directory {
	public function isDirectory()
	{
		$path = $this->uri . "/index.md";
		if ( $this->disk->exists($path) )
		{
			$this->type = "leaflet";
			$this->path = $path;
			$this->exists = true;
		}
	}
}