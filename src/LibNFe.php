<?php
namespace RfWeb\LibNFe;

use NFePHP\NFe\ToolsNFe;
use App\Customer;
/**
 * Biblioteca Nota Fiscal Eletronica
 *
 * Biblioteca customizada a partir da NFePHP (http://www.nfephp.org/) para atender as demandas da empresa Xmltools Soluções Tecnologicas
 * Essa biblioteca trabalha de forma independente sendo necessario apenas chamar as classes e metodos, os parametros e retornos de cada
 * metodo estao documentados nos comentarios de codigo.
 *
 * Duvidas sobre a utilizacao da biblioteca entre em contato pelo e-mail roberson.faria@gmail.com
 *
 * @package   	libNFe
 * @name 		libNFe
 * @version   	1.0.0
 * @copyright 	2014 &copy; Roberson Alvim Faria
 * @link      	http://www.roberson.com.br/
 * @author    	Roberson Faria <roberson.faria@gmail.com>
 * @access 		public
 */
class LibNFe{
	
	private $dadosConfig = Array();
	
	/**
	 *  Faz a consulta no SEFAZ para cada uma das empresas cadastradas
	 *
	 * - Para primeira carga de uma nova empresa o campo config_nfe.nfeconfig_ultnsu deve ser igual a 0 (zero), essa primeira carga vai buscar todas as notas
	 * dos ultimos 15 dias para cada empresa. Na primeira carga deve-se chamar esse metodo quantas vezes forem necessarias ate que tenha como retorno
	 * a seguinte mensagem "Dados atualizados. Nao ha mais NFe para buscar."
	 *
	 * - Para cargas diarias o script se auto executara ate buscar todas as notas de todas as empresas.
	 * Ao final será retornado a seguinte mensagem "Dados atualizados. Nao ha mais NFe para buscar."
	 *
	 * @name	getCustomerNfe
	 * @access	public
	 * @author	Roberson Faria
	 */
	public function getCustomerNFe(){
		try{
			$result = Customer::all()->toArray();
			if(count($result) > 0){
				foreach($result as $config){
					$nfe = new ToolsNFe($this->setConfig($config));
					$xml = $nfe->sefazDistDFe('AN', $this->dadosConfig["tpAmb"], $this->dadosConfig["cnpj"]);
					dd($xml);
					//$this->connectCnpj();
					//$this->getListNFe();
					//$this->disconnectCnpj();
				}
			}
		} catch (Exception $e) {
			Log::error("Erro Exception: " . $e->getMessage());
			exit;
		}
	}
	
	/**
	 * Metodo para instanciar os dados de configuracao que serao utilizados nas buscas na SEFAZ
	 *
	 * @name	setConfig
	 * @access	private
	 * @author	Roberson Faria
	 * @param 	Array $config
	 * @return	grava todos os dados de configuracoes em $this->dadosConfig para uso no restante da classe.
	 */
	private function setConfig(Array $dados){
		//Dados de configuracao ambiente e certificado
		$config = config('LibNFe');
		$dados["customer_cnpj"] = str_pad($dados["customer_cnpj"], 14, "0", STR_PAD_LEFT);
		$config["atualizacao"] = date("Y-m-d H:i:s");
		$config["razaosocial"] = $dados["customer_razao_social"];
		$config["siglaUF"] = 'PR';//$dados["customer_uf"];
		$config["cnpj"] = $dados["customer_cnpj"];
		$config["certPfxName"] = $dados["customer_cnpj"].".pfx";
		$config["certPassword"] = "123456";
		$config["certPhrase"] = "";
		
		$config["pathXmlUrlFileNFe"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathXmlUrlFileNFe"]);
		$config["pathXmlUrlFileCTe"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathXmlUrlFileCTe"]);
		$config["pathXmlUrlFileMDFe"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathXmlUrlFileMDFe"]);
		$config["pathXmlUrlFileCLe"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathXmlUrlFileCLe"]);
		$config["pathXmlUrlFileNFSe"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathXmlUrlFileNFSe"]);
		$config["pathCertsFiles"] = str_replace("{dirProjeto}",$config["dirProjeto"],$config["pathCertsFiles"]).$dados["customer_cnpj"]."/";
		
		$this->dadosConfig = $config;
		return json_encode($config);
	}
	
}
?>