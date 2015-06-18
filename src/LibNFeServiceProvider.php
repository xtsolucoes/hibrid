<?php namespace RfWeb\LibNFe;

use Illuminate\Support\ServiceProvider;

class LibNFeServiceProvider extends ServiceProvider {


	public function boot(){
		$this->publishes([
				__DIR__.'../config/LibNFe.php' => config_path('LibNFe.php'),
		]);
	}
	
    public function register()
    {
        //
    }

}