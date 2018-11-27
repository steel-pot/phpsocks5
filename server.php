<?php
use \Workerman\Worker;
use \Workerman\WebServer;
use \Workerman\Connection\TcpConnection;
use \Workerman\Connection\AsyncTcpConnection;

// 自动加载类
require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('tcp://0.0.0.0:222');
$worker->onConnect = function($connection)
{
   $connection->AsyncConnection=null; 
};


$worker->onClose=function($connection)
{
	if( $connection->AsyncConnection!=null)
	{	 
			$AsyncConnection=$connection->AsyncConnection;
			$connection->AsyncConnection=null;
			$AsyncConnection->close();  
	}
};  

$worker->onMessage = function($connection, $buffer)
{  	 
	$buffer=decode($buffer);
	if($connection->AsyncConnection==null)
	{ 
		//创建代理连接 
		$header=getHeader($buffer);		
		if(!isset($header['header']['Host']))
		{
			//错误操作，没有找到host
			$connection->close();
			return ;
		} 
		//根据主机地址来判断是否需要ssl  tpc
		$host="tcp://";
		if(strpos(':443',$header['header']['Host'])!==false)
		{
			$host="ssl://";
		}
		$host.=$header['header']['Host']; 
		//创建代理连接 
		$connection->AsyncConnection = new AsyncTcpConnection($host);
		$connection->AsyncConnection->onConnect = function($remote_connection)use($connection,$buffer)//不需要引用，只需要创建时的消息
		{ 
			$header=getHeader($buffer); 
			switch($header['method'])
			{
				case 'CONNECT':
					//回复创建成功 
					$connection->send(encode($header['ptl']." 200 Connection Established\r\n\r\n")); 
				break;
				default :
					//转发消息
					$remote_connection->send($buffer);
				break;
			}
		}; 
		$connection->AsyncConnection->onMessage=function($remote_connection, $bf)use($connection){	 
			 $connection->send(encode($bf));
		}; 
		$connection->AsyncConnection->onClose=function($remote_connection)use($connection){
			$connection->AsyncConnection=null;
			$connection->close();
		};  
		$connection->AsyncConnection->connect();
	}else{ 
		$connection->AsyncConnection->send($buffer);
	} 
};
function encode($str)
{
	//return $str;
	$arr=getbytes($str);
	$newarr=array();
	foreach($arr as $a)
	{
		$newarr[]=$a-1;		
	}
	return tostr($newarr);
}
function decode($str)
{
	//return $str;
	$arr=getbytes($str);
	$newarr=array();
	foreach($arr as $a)
	{
		$newarr[]=$a+1;		
	}
	return tostr($newarr);
}
function getbytes($str) {
	$len = strlen($str);
	$bytes = array();
	for($i=0;$i<$len;$i++) {
		if(ord($str[$i]) >= 128){
			$byte = ord($str[$i]) - 256;
		}else{
			$byte = ord($str[$i]);
		}
		$bytes[] =  $byte ;
	}
	return $bytes;
}
     
    /**
     * 将字节数组转化为string类型的数据
     * @param $bytes 字节数组
     * @param $str 目标字符串
     * @return 一个string类型的数据
     */

function tostr($bytes) {
	$str = '';
	foreach($bytes as $ch) {
		$str .= chr($ch);
	}

	return $str;
}  
function getHeader($buffer)
{
	$row=explode("\r\n",$buffer);
	//第一行讲名操作  地址 协议
	$ops=explode(' ',$row[0]);
	if(count($ops)!=3)
	{
		//错误操作，$row[0] 
		return false;
	} 
	
	$header=array();
	$l=count($row);
	for($i=1;$i<$l;$i++)
	{
		$r=explode(': ',$row[$i]);
		if(count($r)<2)$r[]='';
		$header[$r[0]]=$r[1];
	}
	return array('method'=>$ops[0],'url'=>$ops[1],'ptl'=>$ops[2],'header'=>$header);
}  
// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
