<?php
namespace RfWeb\LibNFe;

use NFePHP\NFe\ToolsNFe;
use App\Customer;
use App\Operation;
use App\App;
use Log;
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
	private $retorno = null;
	
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
					$operation =  new Operation();
					$ultnsu = $operation->where('id_customer',$config['id'])->max('resnfe_nsu');
					$nfe = new ToolsNFe($this->setConfig($config));
					$nfe->sefazDistDFe('AN', $this->dadosConfig["tpAmb"], $this->dadosConfig["cnpj"], $ultnsu,0, $this->retorno, false);
					dd($this->retorno);
					foreach($this->retorno['aDoc'] as $dados){
						if($dados["schema"] == "resNFe_v1.00.xsd"){
							$xml = simplexml_load_string ($dados['doc']);
							if(strlen($xml->CNPJ) > 0){
								$doc = $xml->CNPJ;
							}else{
								$doc = $xml->CPF;
							}
							Operation::create([
								'id_customer' =>  $config['id'],
								'resnfe_nsu' =>  $dados['NSU'],
								'resnfe_tpdoc' =>  "1",
								'resnfe_dhrecbtolocal' =>  date('Y-m-d H:i:s'),
								'resnfe_cnpj_cpf' =>  $doc,
								'resnfe_ie' =>  $xml->IE,
								'resnfe_xnome' =>  $xml->xNome,
								'resnfe_chnfe' =>  $xml->chNFe,
								'resnfe_demi' =>  date('Y-m-d H:i:s',strtotime($xml->dhEmi)),
								'resnfe_tpnf' =>  $xml->tpNF,
								'resnfe_csitnfe' =>  $xml->cSitNFe,
	// 							'resnfe_csitconf' =>  $xml->,
								'resnfe_dhrecbto' =>  date('Y-m-d H:i:s',strtotime($xml->dhRecbto)),
								'resnfe_vnf' =>  $xml->vNF,
	// 							'rescce_dhevento' =>  $xml->,
	// 							'rescce_tpevento' =>  $xml->,
	// 							'rescce_nseqevento' =>  $xml->,
	// 							'rescce_descevento' =>  $xml->,
	// 							'rescce_xcorrecao' =>  $xml->,
	// 							'rescce_dhrecbtodefault' =>  $xml->,
								'resnfe_xml' =>  addslashes($dados['doc']),
							]);
							if($xml->cSitNFe == 4 or $xml->cSitNFe == 1){
								$this->downloadXml($config['id'], $xml->chNFe);
							}
// 							echo "res = ".$xml->chNFe."<br>";
						}elseif($dados["schema"] == "procNFe_v1.00.xsd"){
// 							dd($dados);
// 							$xml = simplexml_load_string ($dados['doc']);
// 							dd($xml);
// 							echo "proc = ".$xml->protNFe->infProt->chNFe."<br>";
						}else{
// 							print_r($dados);
// 							echo "<br>";
						}
					}
				}
			}
// 			dd("acabou");
		} catch (Exception $e) {
			Log::warning("Erro LibNfe Exception: " . $e->getMessage());
			exit;
		}
	}
	
	/**
	 * Metodo que recupera do SEFAZ e disponibiliza para download os arquivos
	 *
	 * @name 	downloadXml
	 * @access	public
	 * @author	Roberson Faria
	 * @param 	Numeric $customer_id
	 * @param 	Numeric $chNFe
	 */
	public function downloadXml($customer_id, $chNFe){
		try{
			$customer = Customer::find($customer_id)->toArray();
			$nfe = new ToolsNFe($this->setConfig($customer));
// 			echo "chNfe = ".$chNFe."<br>";
// 			echo "tpAmb = ".$this->dadosConfig["tpAmb"]."<br>";
// 			echo "customer_cnpj = ".$customer['customer_cnpj']."<br>";
			$resp = $nfe->sefazDownload($chNFe, $this->dadosConfig["tpAmb"], '0'.$customer['customer_cnpj'], $aResposta);
			return $aResposta;
		} catch (Exception $e) {
			Log::warning("Erro LibNfe Exception: " . $e->getMessage());
			exit;
		}
	}
	
	/**
	 * Metodo para envio da manifestacao para a SEFAZ
	 *
	 * @name	setManifesto
	 * @access	public
	 * @author	Roberson Faria
	 * @param 	Numeric $customer_id
	 * @param 	Numeric $chNFe
	 * @param 	Numeric $operacao
	 * @param 	String $justificativa
	 * @return	Json $respostaSefaz
	 */
	public function setManifesto($customer_id, $chNFe, $operacao, $xJust = ""){
		$customer = Customer::find($customer_id)->toArray();
		$nfe = new ToolsNFe($this->setConfig($customer));
		$xml = $nfe->sefazManifesta($chNFe, $this->dadosConfig["tpAmb"], $xJust = '', $tpEvento = '', $aResposta);
		return $aResposta;
	}
		
	/**
	 *Método que faz o download da DANFE
	 *
	 * @name 	gerarDANFE
	 * @access	public
	 * @author	Roberson Faria
	 * @param 	Numeric $customer_id
	 * @param 	String $arquivo Caminho para o arquivo xml
	 * @param   Char $tipoDownload Defini o tipo do download do arquivo "I" - abre o pdf no browser "D" - faz o download do PDF para a maquina do cliente.
	 */
	public function printDanfe($arquivo, $tipoDownload = "I"){
		$dxml = base64_decode($xml);
		$logo = 'images/logo.jpg';
		if (strpos($xml, 'recebidas')) {
			$logo = '';
		}
		$docxml = FilesFolders::readFile($xml);
		$danfe = new Danfe($docxml, 'P', 'A4', $logo, $tipoDownload, '');
		$id = $danfe->montaDANFE();
		$danfe->printDANFE($id.'.pdf', $tipoDownload);
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
		
		$config["pathNFeFiles"] = $config["dirProjeto"]."arquivos/".$dados["customer_cnpj"]."";
// 		dd($config["pathNFeFiles"]);
		
		$this->dadosConfig = $config;
		return json_encode($config);
	}
	
}
?>