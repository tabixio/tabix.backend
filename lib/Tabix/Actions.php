<?php
namespace Tabix;

class Actions
{
    /**
     * @var \dotArray
     */
    private $__params=null;

    /**
     * @var ConfigProvider
     */
    private $_configServer=null;

    /**
     * @var DBS\Router
     */
    private $_dbsrouter;

    private $_mongo;

    public function __construct($params)
    {

        if (sizeof($params)<1) throw new \Exception("Empty input body");
        $this->__params=new \dotArray($params);

        $this->_configServer=new ConfigProvider($this->__params->get('auth.confid'));

        $this->_user=new User(
            $this->__params->get('auth.login'),
            $this->__params->get('auth.password'),
            $this->_configServer
            
        );
        if (!$this->user()->isAuth())
        {
            throw new \Exception("Not auth user");
        }

    }

    /**
     * @return DBS\Router
     */
    public function dbs()
    {
        if (!$this->_dbsrouter)
            $this->_dbsrouter=new \Tabix\DBS\Router($this->config(),$this->user(),$this->mongo());
        return $this->_dbsrouter;
    }

    public function mongo()
    {
        if (!$this->_mongo)
        {
            $this->_mongo=new \Tabix\Mongo($this->config(),$this->user());
        }
        return $this->_mongo;
    }

    /**
     * @param bool $key
     * @return array|\dotArray|mixed
     */
    public function param($key=false) {
        if ($key==false) return $this->__params;
        return $this->__params->get($key);
    }
    /**
     * @return User
     */
    public function user() {
        return $this->_user;
    }

    /**
     * @return ConfigProvider
     */
    public function config()
    {
        return $this->_configServer;
    }

    public function actionLogin()
    {
        return ['result'=>"ok"];
    }

    public function actionState($command)
    {
        $out=[];
        $out['mongodb']=($this->mongo()->test());
        return $out;
    }
    public function actionServer($command)
    {
        return ['OK'];
    }
    public function actionKill($id)
    {
        return $this->dbs()->kill($id,$this->param());
    }

    public function actionProcesslist($where)
    {
        return $this->dbs()->processlist($where,$this->param());
    }

    public function actionDescribe($path)
    {
        return $this->dbs()->describe($path,$this->param());
    }

    public function renderResultInFormat($data,$format)
    {
        // @todo : $format in (json , tsv , csv )


        return [];

    }
    public function actionWidget($id)
    {
        $data=[];
        $z= $this->mongo()->widget($id);

        $query=$z['sql'];
        $vars=[];

        if (is_array($z['vars'])) $vars=$z['vars'];
        if (is_array($this->param('vars'))) {
            $vars=array_replace_recursive($vars,$this->param('vars'));
        }

        if (!empty($vars['limit'])) $vars['limit']=intval($vars['limit']);

        if (empty($z['params']['widget']))
        {
            $widget=['type'=>'table'];
        }
        else {
            $widget=$z['params']['widget'];

        }



        $q=new SQLQuery($query,$vars);
        $data=$this->dbs()->query($q,$this->param());
        $draw=$q->extractDraw();



        return [
            'draw'=>$draw,
            'query'=>trim($q->sql()),
            'widget'=>$widget,
            'data'=>$data
        ];
    }
    public function actionFetch()
    {
        $quid=$this->param('quid');
        $sing=$this->param('sign');
        $format=$this->param('format');
        $z= $this->mongo()->fetch($quid,$sing);

        if (!is_array($z)) {
            throw new \Exception('Can`t fecth');
        }

        if ($format)
        {
            return $this->renderResultInFormat($z['data'],$format);
        }

        return $z;
    }

    public function actionDashboards($param=false,$value=false)
    {
        $list=$this->mongo()->dashboards($this->param());
        $tree=[

        ];
        foreach ($list as $id=>$entry)
        {
             if ($entry['path']) {
                 if (is_array($entry['path']))
                 {
                     $p=$entry['path'][0];
                 }else
                 {
                     $p=$entry['path'];
                 }


             }
             else {
                 $p='Main';
             }
            $tree[$p][$entry['id']]=['title'=>$entry['title']];
        }

        return ['tree'=>$tree,'list'=>$list];
    }

    public function actionDrophistory()
    {
        return $this->mongo()->dropHistory();
    }

    public function actionHistory($param=false,$value=false)
    {
        if (!$param) {
            return $this->mongo()->history();
        }
        else {
            return $this->mongo()->historySearch($param,$value);
        }
    }

    public function actionDevelopapi($param=false,$value=false)
    {
        if ($param=='cleandb' && ($this->config()->getId()==='ApiTester'))
        {

            if ($this->param('cleanDatabaseKey')===$this->config()->getMongoDB('cleanDatabaseKey'))
            {

                return ['dev'=>$this->mongo()->cleanDevDatabase($value)];
            }
        }
    }
    public function actionStructure()
    {
        return $this->dbs()->structure($this->param());
    }
    public function actionDashboard($id,$update=false)
    {
        // get by id
        if ($update==='update') {
            // update
            $d=$this->param('dash');
            if ($d){
                return $this->mongo()->dashboardUpdate($id,$d);
            }
        } else {
            return $this->mongo()->dashboard($id);
        }

    }
    public function actionDashboardNew()
    {
        $d=$this->param('dash');
        if ($d){
            return $this->mongo()->dashboardNew($d);
        }
        return ['false'];

    }
    public function actionQuery()
    {
        $query=$this->param('query');
        $vars=$this->param('vars');
        if (!$query) {
            throw new \Exception("Empty Query");
        }

        if (stripos($query,'%TABIX_CHECK_LOGIN%')) {
            return ["meta"=>"1","data"=>["%TABIX_CHECK_LOGIN%",'tabix'=>[]]];
        }

        $q=new SQLQuery($query,$vars);
        return $this->dbs()->query($q,$this->param());
    }
}