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
					$this->setConfig($config);
					$nfe = new ToolsNFe('../../config/config.json');
					$xml = $nfe->sefazDistDFe('AN', $tpAmb, $cnpj, $ultNSU, $numNSU, $aResposta);
					dd($this->config);
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
	private function setConfig(Array $config){
		//Dados de configuracao ambiente e certificado
		$dados["ambiente"] 		= $config["nfeconfig_ambiente"];
		$dados["certName"] 		= $config["nfeconfig_pfx"];
		$dados["keyPass"] 		= $config["nfeconfig_key_pass"];
		$dados["passPhrase"] 	= $config["nfeconfig_pass_phrase"];
		$dados["arquivosDir"] 	= $config["nfeconfig_arquivos_dir"];
		$dados["arquivoURLxml"] = $config["nfeconfig_arquivo_url_xml"];
		$dados["baseurl"] 		= $config["nfeconfig_base_url"];
		$dados["danfeLogo"] 	= $config["nfeconfig_danfe_logo"];
		$dados["danfeLogoPos"] 	= $config["nfeconfig_danfe_logo_pos"];
		$dados["danfeFormato"] 	= $config["nfeconfig_danfe_formato"];
		$dados["danfePapel"] 	= $config["nfeconfig_danfe_papel"];
		$dados["danfeCanhoto"] 	= $config["nfeconfig_danfe_canhoto"];
		$dados["danfeFonte"] 	= $config["nfeconfig_danfe_font"];
		$dados["danfePrinter"] 	= $config["nfeconfig_danfe_printer"];
		$dados["schemes"] 		= $config["nfeconfig_schemes"];
		$dados["proxyIP"] 		= $config["nfeconfig_proxy_ip"];
		$dados["mailFROM"] 		= $config["nfeconfig_mail_from"];
	
		//Dados empresa
		$dados["empresa"]		= $config["nfeconfig_empresa"];
		$dados["UF"]			= $config["nfeconfig_uf"];
		$dados["cUF"]			= $config["nfeconfig_cuf"];
		$dados["cnpj"]			= (String)$config["nfeconfig_cnpj"];
	
		//Dados de controle da classe
		$dados["customer_id"]	= $config["nfeconfig_customer_id"];
		$dados["ultNSU"]		= $config["nfeconfig_ultnsu"];
		$dados["modSOAP"] 		= '2'; //usando cURL
		$dados["indNFe"] 		= '0';
		$dados["indEmi"] 		= '1';
		$dados["AN"] 			= TRUE;
		$dados["indCon"] 		= 1;
	
		//Grava no atributo da classe
		$this->dadosConfig 		= $dados;
	}
	
}
?>