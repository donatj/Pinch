#!/usr/bin/php -q
<?php

if( is_file('local/config.ini') ) {
	$config = parse_ini_file('local/config.ini', true);
}else{
	$config = parse_ini_file('config.ini', true);
}

include('pinch.php');

$pinch = new Pinch(
	$config['server']['path'], 
	$config['bot']['name'], 
	$config['server']['channel'], 
	$config['server']['password']
);

/*
$pinch->on('/:test ([a-z0-9 ]+)/i', function($obj, $msg, $info){
	$obj->msg( $msg[1], $info['reply'] );
});
*/

$pinch->onmsg('/\b(https?):\/\/[A-Z][-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', function($obj, $msg, $info){
	$page = file_get_contents( $msg[0] );
	$page = preg_replace('%<(script|style|noscript|title|textarea|th|head)[^>]*>(.*?)</\1>%s', ' ', $page);


	do {
		$prev_page = $page;
		$page = preg_replace('%<(i|b|span|strong|th|head|h1|h2|h3|h4|h5)[^>]*>(?P<content>[^>]*)</\1>%', ' $2 ', $page);
	}while( $prev_page != $page );

	$page = preg_replace('/[ \t]+/i', ' ', $page);
	$page = preg_replace('%<([a-z]+)[^>]*>[^>]{0,20}</\1>%', ' ', $page);
	
	$page = preg_replace('/\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', ' [link] ', $page);
	

	$page = preg_replace('/ +/i', ' ', preg_replace('/[^a-zA-Z0-9.,\\[\\] ]/', ' ', html_entity_decode(strip_tags($page))));
	
	$page = `osascript -e 'return summarize "{$page}" in 3'`;
	$obj->msg( 'Summary: ' . substr($page, 0, 500), $info['reply'] );
});


$pinch->onmsg('/(\?|Do|Did|Is|Are|What|Where|Who|Why|When|How)( |\?)(.*)\?/i', function($obj, $msg, $info){
	global $config;

	$search = urlencode( trim($msg[0],'? ') );
	
	$url = 'http://api.wolframalpha.com/v2/query?input='.$search.'&format=plaintext&appid=' . $config['wolfram']['apikey'];
	echo PHP_EOL . $url . PHP_EOL;
	$data = file_get_contents($url);

	$doc = new DOMDocument();
	$doc->loadXML($data);

	$pts = $doc->getElementsByTagName('plaintext');
	$count = 0;
	foreach( $pts as $pt ) {
		$obj->msg( $pt->textContent, $info['reply']);
		if( $count++ > 2 ) { break; }
	}

});

$pinch->on('/ (fake|false|lie|artifical)[. ]/i', function($obj, $msg, $info){
	$data = json_decode( file_get_contents('http://twitter.com/status/user_timeline/fakehenrymoy.json?count=100'), true );
	$msg  = $data[array_rand($data)]['text'];
	$msg  = str_replace('"', ' ', $msg);
	$msg  = trim($msg);
	$obj->msg($msg, $info['reply']);
});



$pinch->connect();
