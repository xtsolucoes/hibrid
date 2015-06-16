<?php 
namespace RfWeb\LibNFe;

use NFePHP\NFe;
use App\User;
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
class libNFe{
	private $nfe = NULL;
	private $dadosConfig = Array();
	private $ultNSU = NULL;
	private $retorno = Array();
	private $log = NULL;
	
	/**
	 * Metodo construtor, por enquanto vazio.
	 * 
	 * @access 	public
	 * @author 	Roberson Faria
	 * @name 	__construct
	 */
	public function __construct(){
		$users = User::all();
		dd($users);
	}
	
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
			$result = $this->getCustomer();
			if(count($result) > 0){
				foreach($result as $config){
					$this->setConfig($config);
					$this->connectCnpj();
					$this->getListNFe();
					$this->disconnectCnpj();
				}
			}
		} catch (Exception $e) {
			$txt = "Erro Exception: " . $e->getMessage();
			$this->log->file($txt);
			die();
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
	public function setManifesto($customer_id, $chNFe, $operacao, $justificativa = ""){
		$respSefaz = null;
		$result = $this->getCustomers($customer_id);
		if(count($result) == 1){
			$this->setConfig($result[0]);
			$this->connectCnpj();
			
			$xml = $nfe->manifDest($chNFe,$operacao,$justificativa,$this->dadosConfig["AN"],$this->dadosConfig["modSOAP"],$respSefaz);
			$this->log->debug($respSefaz);
			
			$this->log->debug($xml);
			
			$this->disconnectCnpj();
			
			return json_encode($respSefaz);
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
			$result = $this->getCustomers($customer_id);
			if(count($result) == 1){
				$this->setConfig($result[0]);
				$this->connectCnpj();
				$resp = $nfe->getNFe($this->dadosConfig["AN"], $chNFe, $this->dadosConfig["ambiente"], $this->dadosConfig["modSOAP"]);
				echo print_r($resp);
				echo '<BR>';
				echo $nfe->errMsg.'<BR>';
				echo '<PRE>';
				echo htmlspecialchars($nfe->soapDebug);
				echo '</PRE><BR>';
				
				$this->disconnectCnpj();
			}
		} catch (nfephpException $e){
			$txt = "Erro nfephpException: " . $e->getMessage();
			$this->log->file($txt);
			die();
		}
	}
	
	/**
	 * Metodo que consulta a tabela de configuracoes, pega todas as empresas cadastradas.
	 * @name	getCustomers
	 * @access	private
	 * @author	Roberson Faria
	 * @param 	Numeric $customer_id
	 * @return	Array $customers
	 */
	private function getCustomers($customer_id = NULL){
		try{
			$sql = "SELECT * FROM config_nfe";
			if(!is_null($customer_id)){
				$sql .= " WHERE nfeconfig_customer_id = ".$customer_id;
			}
			return $this->db->query($sql)->fetchAll();
		} catch (PDOException $e) {
			$txt = "Erro PDOException: " . $e->getMessage();
			$this->log->file($txt);
			die();
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
	
	/**
	 * Metodo que instancia a classe ToolsNFePHP criando a conexao entre a empresa configurada e o SEFAZ
	 * 
	 * @name	connectCnpj
	 * @access	private
	 * @author	Roberson Faria
	 */
	private function connectCnpj(){
		$this->nfe = new ToolsNFePHP($this->dadosConfig,1,true);
	}
	
	/**
	 * Metodo que mata a instancia a classe ToolsNFePHP e limpa os dados das configuracoes
	 *
	 * @name	connectCnpj
	 * @access	private
	 * @author	Roberson Faria
	 */
	private function disconnectCnpj(){
		$this->nfe = NULL;
		$this->dadosConfig = Array();
	}
	
	/**
	 * Metodo responsavel por buscar NFe na SEFAZ e gravar no banco de dados
	 * 
	 * @name	getListNFe
	 * @access	private
	 * @author	Roberson Faria
	 */
	private function getListNFe(){
		try{
			//Busca NFe no SEFAZ e retorna os dados no atributo $this->retorno
			$xml = $this->nfe->getListNFe($this->dadosConfig["AN"], $this->dadosConfig["indNFe"], $this->dadosConfig["indEmi"], $this->getUltNSU(), 
											$this->dadosConfig["ambiente"], $this->dadosConfig["modSOAP"], $this->retorno);
			
			if($this->retorno != null){
				$nfe = null;
				if(count($this->retorno["NFe"]) > 0){
					foreach($this->retorno["NFe"] as $nfe){
						if(strlen($nfe["CNPJ"]) > 0){
							$doc = $nfe["CNPJ"];
						}else{
							$doc = $nfe["CPF"];
						}
						$sql = "INSERT INTO retconsnfedest1 (resnfe_nsu,resnfe_tpdoc, resnfe_dhrecbtolocal, resnfe_cnpj_cpf,resnfe_ie, resnfe_xnome, resnfe_chnfe
										, resnfe_demi,resnfe_tpnf, resnfe_csitnfe, resnfe_csitconf,resnfe_dhrecbto, resnfe_vnf, id_customer) 
									   VALUES (".$nfe["NSU"].",1,'".date("Y-m-d H:i:s")."','".$doc."','".$nfe["IE"]."','".$nfe["xNome"]."',".$nfe["chNFe"]."
										,'".$nfe["dEmi"]."',".$nfe["tpNF"].",".$nfe["cSitNFe"].",".$nfe["cSitconf"].",'".$nfe["dhRecbto"]."','".$nfe["vNF"]."',".$this->dadosConfig["customer_id"].")";
						$this->db->query($sql);
					}
				}elseif(count($this->retorno["Canc"]) > 0){
					//imprime array de cancelados
					//precisa ser conferido ainda esse retorno e a sql.
					$this->log->debug($this->retorno["Canc"]);
					foreach($this->retorno["Canc"] as $nfe){
						if(strlen($nfe["CNPJ"]) > 0){
							$doc = $nfe["CNPJ"];
						}else{
							$doc = $nfe["CPF"];
						}
						$sql = "INSERT INTO retconsnfedest1 (resnfe_nsu,resnfe_tpdoc, resnfe_dhrecbtolocal, resnfe_cnpj_cpf,resnfe_ie, resnfe_xnome, resnfe_chnfe
										, resnfe_demi,resnfe_tpnf, resnfe_csitnfe, resnfe_csitconf,resnfe_dhrecbto, resnfe_vnf, id_customer) 
									   VALUES (".$nfe["NSU"].",1,'".date("Y-m-d H:i:s")."','".$doc."','".$nfe["IE"]."','".$nfe["xNome"]."',".$nfe["chNFe"]."
										,'".$nfe["dEmi"]."',".$nfe["tpNF"].",".$nfe["cSitNFe"].",".$nfe["cSitconf"].",'".$nfe["dhRecbto"]."','".$nfe["vNF"]."',".$this->dadosConfig["customer_id"].")";
					}
				}elseif(count($this->retorno["CCe"]) > 0){
					//imprime array de CCe
					//precisa ser conferido ainda esse retorno e a sql.
					$this->log->debug($this->retorno["CCe"]);
					foreach($this->retorno["CCe"] as $nfe){
						if(strlen($nfe["CNPJ"]) > 0){
							$doc = $nfe["CNPJ"];
						}else{
							$doc = $nfe["CPF"];
						}
						$sql = "INSERT INTO retconsnfedest1 (resnfe_nsu,resnfe_tpdoc, resnfe_dhrecbtolocal, resnfe_cnpj_cpf,resnfe_ie, resnfe_xnome, resnfe_chnfe
										, resnfe_demi,resnfe_tpnf, resnfe_csitnfe, resnfe_csitconf,resnfe_dhrecbto, resnfe_vnf, id_customer) 
									   VALUES (".$nfe["NSU"].",1,'".date("Y-m-d H:i:s")."','".$doc."','".$nfe["IE"]."','".$nfe["xNome"]."',".$nfe["chNFe"]."
										,'".$nfe["dEmi"]."',".$nfe["tpNF"].",".$nfe["cSitNFe"].",".$nfe["cSitconf"].",'".$nfe["dhRecbto"]."','".$nfe["vNF"]."',".$this->dadosConfig["customer_id"].")";
					}
				}
				//grava o ultimo NSU
				$this->setUltNsu($this->retorno["ultNSU"]);
				
				//descarrega buffer de tela
				flush();
				
				//caso ainda não tenha chego ao fim da busca, chama novamente o metodo para buscar as proximas NFe;
				if($this->retorno["indCont"] != 0){
					return $this->getListNFe();
				}
			}
			return true;
		} catch (nfephpException $e){
			$txt = "Erro nfephpException: " . $e->getMessage();
			$this->log->file($txt);
			die();
		} catch (Exception $e) {
			$txt = "Erro Exception: " . $e->getMessage();
			$this->log->file($txt);
			die();
		}
	}
	
	/**
	 * Metodo para gravar o ultimo NSU pesquisado.
	 * 
	 * @name	setUltNsu
	 * @access	private
	 * @author	Roberson Faria
	 * @param 	Numeric $ultNSU
	 */
	private function setUltNsu(Numeric $ultNSU){
		try{
			$this->ultNSU = $ultNSU;
			$sql = "UPDATE config_nfe SET nfeconfig_ultnsu = '".$ultNSU."' WHERE nfeconfig_cnpj = '".$this->dadosConfig["cnpj"]."'";
			$this->db->query($sql);
		} catch (PDOException $e) {
			$txt = "Erro PDOException: " . $e->getMessage();
			$this->log->file($txt);
			die();
		} catch (Exception $e) {
			$txt = "Erro Exception: " . $e->getMessage();
			$this->log->file($txt);
			die();
		}
	}
	
	/**
	 * Metodo para retornar o ultimo NSU pesquisado.
	 *
	 * @name	getUltNsu
	 * @access	private
	 * @author	Roberson Faria
	 * @return	Numeric $ultNSU
	 */
	private function getUltNSU(){
		if($this->ultNSU == null){
			$this->ultNSU = $this->dadosConfig["ultNSU"];
		}
		return $this->ultNSU;
	}
	
	/**
	 * Descartado por enquanto. 
	private function saveNFe(){
		$dados = $this->retorno;
		//if()
		$this->log->debug($dados);
	}
	
	function importaNFe($xml){
		$doc = new DOMDocument();
		//Elimica espacos em Branco
		$doc->preservWhiteSpace = FALSE;
		$doc->formatOutput = FALSE;
		$doc->loadXML($xml,LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
		$node = $doc->getElementsByTagName('nfeConsultaNFDestResult')->item(0);
		$this->log->debug($node);
	
		//obtem a versão do layout da NFe
		$dados['versao']=trim($node->getAttribute("versao"));
		$dados['chave']= substr(trim($node->getAttribute("Id")),3);
	
		return($dados);
	}**/
}
?>