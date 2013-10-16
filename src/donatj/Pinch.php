<?php

namespace donatj;

use CLI\Misc;
use CLI\Style;

class Pinch {

	var $conn;

	const OUTPUT_ROWS = 15;

	var $user, $channel, $server, $password, $port;

	private $events = array( 'on' => array(), 'msg' => array() );

	function __construct( $server, $user, $channel, $password = false, $port = 6667 ) {
		$this->server   = $server;
		$this->user     = $user;
		$this->channel  = $channel;
		$this->password = $password;
		$this->port     = $port;
	}

	function send( $msg ) {
		echo Style::red("CMD --> ") . "{$msg}\n";
		$this->statusbar($msg, true);

		return fwrite($this->conn, $msg . "\n");
	}

	function msg( $msg, $recepient = false ) {
		echo Style::purple("MSG --> ") . "{$msg}\n";
		$lines = explode(PHP_EOL, $msg);
		foreach( $lines as $line ) {
			$line = trim($line);
			if( $line && $line[0] != '(' ) {
				$this->send('PRIVMSG ' . $recepient . ' :' . $line);
			}
		}
	}

	private function statusbar( $str, $in = false ) {
		static $lines = false;
		if( !$lines ) {
			$lines = array_fill(0, self::OUTPUT_ROWS, array( 'str' => '', 'in' => '' ));
		}
		$lines[] = array( 'str' => $str, 'in' => $in );
		array_shift($lines);

		echo "\0337\033[1;1f\033[2K";
		foreach( $lines as $line ) {
			$text = str_pad(substr(($line['in'] ? ' <  ' : ' >  ') . $line['str'], 0, Misc::cols()), Misc::cols());
			if( $line['in'] ) {
				echo Style::red($text, 'light_gray');
			} else {
				echo Style::black($text, 'light_gray');
			}
		}

		echo str_repeat('─', Misc::cols());

		echo "\0338";
	}

	function connect() {

		echo "\033[2J\033[1;1f"; //clear screen
		echo str_repeat(PHP_EOL, self::OUTPUT_ROWS + 2);

		$this->conn = fsockopen($this->server, $this->port, $errno, $errstr);

		if( $this->password ) {
			$this->send("PASS " . $this->password);
		}
		$this->send("USER username hostname servername :real name");
		$this->send("NICK " . $this->user);

		while( $input = trim(fgets($this->conn)) ) {
			stream_set_timeout($this->conn, 3600);

			if( preg_match("|^PING :(.*)$|i", $input, $matches) ) {
				$this->send("PONG :{$matches[1]}");
				$this->send("JOIN " . $this->channel);
				break;
			}

			$this->statusbar($input);
		}

		while( $input = trim(fgets($this->conn)) ) {
			stream_set_timeout($this->conn, 3600);

			switch( true ) {
				//keep alive
				case(preg_match("|^PING :(.*)$|i", $input, $matches)):
					echo Style::light_gray("[ SERVER PING {$matches[1]} ]\n");
					$this->send("PONG :{$matches[1]}");
					break;
				//messages with recipients
				case(preg_match("|^:(?P<from>.+?)!.* (?P<cmd>[A-Z]*) (?P<to>.+?) :(?P<msg>.*)$|i", $input, $matches)):
					$text = sprintf("%-12s%s -> %s: %s\n", $matches["cmd"], $matches["from"], $matches["to"], $matches["msg"]);

					if( $matches["to"][0] == '#' ) {
						$matches['reply'] = $matches["to"];
						echo Style::cyan($text);
					} else {
						$matches['reply'] = $matches["from"];
						echo Style::light_cyan($text);
					}

					foreach( $this->events['msg'] as $event ) {
						if( preg_match($event['match'], $event['justmsg'] ? $matches["msg"] : $input, $lmatches) ) {
							$event['lambda']($this, $lmatches, $matches);
						}
					}

					break;
				//messages without recipients
				case(preg_match("|^:(?P<from>.+?)!.* (?P<cmd>[A-Z]*) :(?P<msg>.*)$|i", $input, $matches)):
					echo Style::blue(sprintf("%-12s%s <- %s\n", $matches["cmd"], $matches["msg"], $matches["from"]));
					break;
				//kick everything else out to our shell
				default:
					foreach( $this->events['on'] as $event ) {
						if( preg_match($event['match'], $event['justmsg'] ? $matches["msg"] : $input, $lmatches) ) {
							$event['lambda']($this, $lmatches);
						}
					}
					break;
			}

			$this->statusbar($input);
		}
	}

	public function on( $regex, $lambda ) {
		$this->events['on'][] = array( 'match' => $regex, 'lambda' => $lambda );
	}

	public function onmsg( $regex, $lambda, $justmsg = true ) {
		$this->events['msg'][] = array( 'match' => $regex, 'lambda' => $lambda, 'justmsg' => $justmsg );
	}

}
