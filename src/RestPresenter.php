<?php
namespace occ2\RestPresenter;

use Ublaboo\ApiRouter\ApiRoute;

/**
 * Abstract presenter for easy build REST API interfaces
 * it use ublaboo/api-router annotations
 * @abstract
 * @author Milan Onderka
 * @package occ2/rest-presenter
 * @version 1.0.0
 */
abstract class RestPresenter extends \Nette\Application\UI\Presenter{
    const PROTOCOL_PREFIX="https://";
    const RESPONSE_MIMETYPE="application/json";
    const RESPONSE_CHARSET="charset=utf-8";
    
    const DEFAULT_ITEMS_PER_PAGE=0;
    const DEFAULT_PAGE_NUMBER=0;
    
    const HTTP_SUCCESS_CODE=200;
    const HTTP_SUCCESS_TITLE="OK";
    
    const HTTP_CREATED_CODE=201;
    const HTTP_CREATED_TITLE="Created";
    
    const HTTP_NO_CONTENT_CODE=204;
    const HTTP_NO_CONTENT_TITLE="No Content";
    
    const HTTP_BAD_REQUEST_CODE=400;
    const HTTP_BAD_REQUEST_TITLE="Bad Request";
    const HTTP_BAD_REQUEST_MESSAGE="Request could not be solved because of syntax error";
    
    const HTTP_UNAUTHORIZED_CODE=401;
    const HTTP_UNAUTHORIZED_TITLE="Unauthorized";
    const HTTP_UNAUTHORIZED_MESSAGE="Authorization required";
    
    const HTTP_FORBIDDEN_CODE=403;
    const HTTP_FORBIDDEN_TITLE="Forbidden";
    const HTTP_FORBIDDEN_MESSAGE="You are not allowed to do this action";
    
    const HTTP_NOT_FOUND_CODE=404;
    const HTTP_NOT_FOUND_TITLE="Not Found";
    const HTTP_NOT_FOUND_MESSAGE="The requested resource could not be found";
    
    const HTTP_METHOD_NOT_ALLOWED_CODE=405;
    const HTTP_METHOD_NOT_ALLOWED_TITLE="Method Not Allowed";
    const HTTP_METHOD_NOT_ALLOWED_MESSAGE="List of supported methods is in Allow header";
    
    const HTTP_VALIDATION_FAILED_CODE=422;
    const HTTP_VALIDATION_FAILED_TITLE="Unprocessable Entity";
    const HTTP_VALIDATION_FAILED_MESSAGE="Validation failed";
    
    const HTTP_INTERNAL_SERVER_ERROR_CODE=500;
    const HTTP_INTERNAL_SERVER_ERROR_TITLE="Internal server error";
    const HTTP_INTERNAL_SERVER_ERROR_MESSAGE="Your request caused internal server error.";
    
    const DB_DATE_FORMAT="%d.%m.%Y %H:%i:%s";
    const NETTE_DB_DATE_FORMAT="d.m.Y H:i:s";
    
    /**
     * @var null
     */
    public $translator = null;
    
    /**
     * list of allowed methods (default GET and OPTIONS only)
     * @var array
     */
    public $allowedMethods=[
        "GET",
        "OPTIONS"
    ];
        
    /**
     * send JSON and HTTP code response
     * @param array $array
     * @param type $code
     * @return \Nette\Http\Response
     */
    public function send(array $array,$code=self::HTTP_SUCCESS_CODE,$headers=null){
        if($code!=null){
            $this->getHttpResponse()->setCode($code);
        }
        if($headers!=null){
            foreach($headers as $key=>$value){
               $this->getHttpResponse()->addHeader($key,$value); 
            }        
        }
        return $this->sendResponse(new \Nette\Application\Responses\JsonResponse($array, self::RESPONSE_MIMETYPE . ";" . self::RESPONSE_CHARSET));
    }
    
    /**
     * return not implemented error message
     * @return \Nette\Http\Response
     */
    public function notImplemented(){
        $this->send([
                        "status"=>"error",
                        "name"=>(!$this->translator instanceof \Nette\Localization\ITranslator) ? self::HTTP_METHOD_NOT_ALLOWED_TITLE : $this->translator->translate(self::HTTP_METHOD_NOT_ALLOWED_TITLE),
                        "message"=>(!$this->translator instanceof \Nette\Localization\ITranslator) ? self::HTTP_METHOD_NOT_ALLOWED_MESSAGE : $this->translator->translate(self::HTTP_METHOD_NOT_ALLOWED_MESSAGEE),
                        "status"=>self::HTTP_METHOD_NOT_ALLOWED_CODE
        ],self::HTTP_METHOD_NOT_ALLOWED_CODE);
        return;
    }
    
    /**
     * send REST API error
     * @param string $name
     * @param string $message
     * @param integer $code
     * @return void
     */
    public function sendError($name,$message,$code){
        $this->send([
                        "status"=>"error",
                        "name"=>(!$this->translator instanceof \Nette\Localization\ITranslator) ? $name : $this->translator->translater($name),
                        "message"=>(!$this->translator instanceof \Nette\Localization\ITranslator) ? $message : $this->translator->translate($message),
                        "code"=>$code
        ],$code);
        return;        
    }
    
    /**
     * send REST data
     * @param array $data
     * @param array $addons
     * @param integer $code
     * @param array $links
     * @return void
     */
    public function sendData($data=[],$addons=[],$code=self::HTTP_SUCCESS_CODE,$links=null){
        if($links!=null){
            $url = $this->getHttpRequest()->getUrl();
            foreach($links as $link){
                if(\Nette\Utils\Strings::contains($link, ":")){
                        $data["_links"][] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . $this->link($link, ["id"=>$data["id"]]);
                    }
                else{
                        $data["_links"][] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . "/" . $link . "/" . $data["id"];
                    }                
            }
        }
        $url = $this->getHttpRequest()->getUrl();
        if(isset($addons["links"])){
            foreach($addons["links"] as $key=>$addon){
                if(\Nette\Utils\Strings::contains($addon, ":")){
                        $addons["_links"][$key] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . $this->link($addon);
                    }
                else{
                        $addons["_links"][$key] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . "/" . $addon;
                    } 
            }
        }
        $addons["self"] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . $this->link("this");
        $this->send([
                        "status"=>"ok",
                        "data"=>$data,
                        "addons"=>$addons,
        ],$code);
        return ;
    }
    
    /**
     * send Allow http header response
     * @return \Nette\Http\Response
     */
    public function actionOptions(){
        $this->send([
            "Allow"=> implode(", ", $this->allowedMethods)
        ], self::HTTP_SUCCESS_CODE, [
            "Allow"=> implode(", ", $this->allowedMethods)
        ]);
        return;
    }
    
    /**
     * send REST data with filters, ordering, paginating, count etc..
     * @param \Nette\Database\Table\Selection $source
     * @param array $addons list of addons parameters
     * @param integer $code returned HTTP code
     * @param integer $page page number for paginating
     * @param integer $items items per page for paginating
     * @param string $order row-asc/row-desc for ordering
     * @param array $filter array of LIKE filters
     * @param string $fields comma separated list of fields
     * @param array $links
     * @param boolean $count return only count of results?
     * @return void
     */
    public function sendDataExtended(\Nette\Database\Table\Selection $source,$addons=[],$code=self::HTTP_SUCCESS_CODE,$page=self::DEFAULT_ITEMS_PER_PAGE,$items=self::DEFAULT_PAGE_NUMBER,$order="",$filter=null,$fields="",$links=null,$count=false){
        
        if($filter!=null && is_array($filter)){
            foreach($filter as $key=>$value){
                $source->where($key . " LIKE ?",$value);
            }
        }

        if($count==true){
            $this->sendData($source->count("*"), $addons, $code);
            return;
        }
        
        if($order!=""){
            $_ar = explode("-", $order);
            if(isset($_ar[1]) && $_ar[1]="desc"){
                $source->order($_ar[0] . " DESC");
            }
            else{
                $source->order($_ar[0]);
            }
        }
        
        if($page!=0 && $items!=0){
            $url=$this->getHttpRequest()->getUrl();
            $paginator = new \Nette\Utils\Paginator;
            $_source = clone $source;
            $_count=$_source->count("*");
            $paginator->setItemCount($_count);
            $paginator->setItemsPerPage($items);
            $paginator->setPage($page);
            $source->limit($paginator->getLength(), $paginator->getOffset());
            $addons["_totalCount"]=$_count;
            $addons["_itemsPerPage"]=$items;
            $addons["_totalPages"]=ceil($_count/$items);
            $addons["_currentPage"]=$page;
            $addons["_currentPageLink"]=$url;
            if(!$paginator->first){
                $previousUrl = clone $url;
                $previousUrl->setQueryParameter("page", $page-1);
                $addons["_previousPageLink"]=$previousUrl;
            }
            if(!$paginator->last){
                $nextUrl = clone $url;
                $nextUrl->setQueryParameter("page", $page+1);
                $addons["_nextPageLink"]=$nextUrl;
            }          
        }  
                
        if($fields!=""){
            if(\Nette\Utils\Strings::contains($fields, "DATE_FORMAT")){
                $source->select($fields, self::DB_DATE_FORMAT);
            }
            else{
                $source->select($fields); 
            }
        }
        elseif($fields==false){}
        else{
            $source->select("*");            
        }
        if($links!=null){
            $url = $this->getHttpRequest()->getUrl();
            $data=[];
            foreach ($source as $key=>$values){
                $data[$key] = $values->toArray();
                foreach($links as $link){
                    if(\Nette\Utils\Strings::contains($link, ":")){
                        $data[$key]["_links"][] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . $this->link($link, ["id"=>$data[$key]["id"]]);
                    }
                    else{
                        $data[$key]["_links"][] = self::PROTOCOL_PREFIX . $url->getUser() . ":" . $url->getPassword() . "@" . $url->getHost() . $url->getPath() . "/" . $data[$key]["id"] . "/" . $link;
                    }                    
                }
            }
            $this->sendData($data, $addons, $code);
        }
        else{
            $this->sendData(array_map('iterator_to_array', $source->fetchAll()), $addons, $code);
        }
        return;
    }

    /**
     * search in database and send REST data with filters, ordering, paginating, count etc..
     * @param \Nette\Database\Table\Selection $source
     * @param string $text
     * @param array $searchedRows
     * @param array $addons list of addons parameters
     * @param integer $code returned HTTP code
     * @param integer $page page number for paginating
     * @param integer $items items per page for paginating
     * @param string $order row-asc/row-desc for ordering
     * @param array $filter array of LIKE filters
     * @param string $fields comma separated list of fields
     * @param boolean $count return only count of results?
     * @return void
     */
    public function search(\Nette\Database\Table\Selection $source,$text,$searchedRows,$addons=[],$code=self::HTTP_SUCCESS_CODE,$page=self::DEFAULT_PAGE_NUMBER,$items=self::DEFAULT_ITEMS_PER_PAGE,$order="",$filter=null,$fields="",$links=null,$count=false){
       $conditions=[];
        foreach($searchedRows as $searchedRow){
           $conditions[$searchedRow . " LIKE ?"]="%" . $text . "%";
           
       }
       $source->whereOr($conditions);
       return $this->sendDataExtended($source, $addons, $code, $page, $items, $order, $filter, $fields, $links,$count); 
    }
}
