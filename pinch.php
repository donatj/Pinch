<?php

class Pinch {

	var $conn;

	var $user, $channel, $server, $password, $port;

	private $events = array();

	function __construct( $server, $user, $channel, $password = false, $port = 6667 ) {
		$this->server   = $server;
		$this->user     = $user;
		$this->channel  = $channel;
		$this->password = $password;
		$this->port     = $port;
	}

	function send( $msg ){
		echo ColorCLI::red("CMD --> ") . "{$msg}\n";
		return fwrite( $this->conn, $msg . "\n" );
	}

	function msg( $msg, $recepient = false ) {
		echo ColorCLI::purple("MSG --> ") . "{$msg}\n";
		$lines = explode( PHP_EOL, $msg );
		foreach( $lines as $line ) {
			$line = trim($line);
			if( $line && $line[0] != '(' ) {
				$this->send( 'PRIVMSG ' . $recepient . ' :' . $line );	
			}
		}
	}

	function connect() {
		$this->conn = fsockopen( $this->server, $this->port, $errno, $errstr );

		if( $this->password ) {
			$this->send("PASS " . $this->password );
		}
		$this->send("USER username hostname servername :real name" );
		$this->send("NICK " . $this->user    );
		$this->send("JOIN " . $this->channel );

		while ( $input = trim( fgets( $this->conn ) ) ) {
			stream_set_timeout( $this->conn, 3600 );
			switch( true ){
				//keep alive
				case( preg_match("|^PING :(.*)$|i", $input, $matches ) ):
					echo ColorCLI::light_gray("[ SERVER PING {$matches[1]} ]\n");
					$this->send("PONG :{$matches[1]}" );
				break;
				//messages with recipients
				case( preg_match("|^:(?P<from>.+?)!.* (?P<cmd>[A-Z]*) (?P<to>.+?) :(?P<msg>.*)$|i", $input, $matches ) ):
					echo ColorCLI::cyan(sprintf( "%-12s%s -> %s: %s\n", $matches["cmd"], $matches["from"], $matches["to"], $matches["msg"] ));

					if( $matches["to"][0] == '#' ) {
						$matches['reply'] = $matches["to"];
					}else{
						$matches['reply'] = $matches["from"];
					}

					foreach( $this->events as $event ) {
						if( preg_match($event['match'], $input, $lmatches) ) {
							$event['lambda']( $this, $lmatches, $matches );
						}
					}

				break;
				//messages without recipients
				case( preg_match("|^:(?P<from>.+?)!.* (?P<cmd>[A-Z]*) :(?P<msg>.*)$|i", $input, $matches ) ):
					echo ColorCLI::light_cyan(sprintf( "%-12s%s <- %s\n", $matches["cmd"], $matches["msg"], $matches["from"] ));
				break;
				//kick everything else out to our shell
				default:
					echo $input, "\n";
				break;
			}

		}
	}

	function on($regex, $lambda) {
		$this->events[] = array('match' => $regex, 'lambda' => $lambda);
	}

}


class ColorCLI {

	static $foreground_colors = array(
		'bold'         => '1',    'dim'          => '2',
		'black'        => '0;30', 'dark_gray'    => '1;30',
		'blue'         => '0;34', 'light_blue'   => '1;34',
		'green'        => '0;32', 'light_green'  => '1;32',
		'cyan'         => '0;36', 'light_cyan'   => '1;36',
		'red'          => '0;31', 'light_red'    => '1;31',
		'purple'       => '0;35', 'light_purple' => '1;35',
		'brown'        => '0;33', 'yellow'       => '1;33',
		'light_gray'   => '0;37', 'white'        => '1;37',
		'normal'       => '0;39',
	);

	static $background_colors = array(
		'black'        => '40',   'red'          => '41',
		'green'        => '42',   'yellow'       => '43',
		'blue'         => '44',   'magenta'      => '45',
		'cyan'         => '46',   'light_gray'   => '47',
	);

	static $options = array(
		'underline'    => '4',    'blink'         => '5', 
		'reverse'      => '7',    'hidden'        => '8',
	);

	public static function __callStatic( $foreground_color, $args ) {

		$string = $args[0];		
		$colored_string = "";

		// Check if given foreground color found
		if( isset(self::$foreground_colors[$foreground_color]) ) {
			$colored_string .= "\033[" . self::$foreground_colors[$foreground_color] . "m";
		}else{
			die( $foreground_color . ' not a valid color');
		}

		array_shift($args);
		foreach( $args as $option ){
			// Check if given background color found
			if(isset(self::$background_colors[$option])) {
				$colored_string .= "\033[" . self::$background_colors[$option] . "m";
			}elseif(isset(self::$options[$option])) {
				$colored_string .= "\033[" . self::$options[$option] . "m";
			}
		}

		// Add string and end coloring
		$colored_string .= $string . "\033[0m";

		return $colored_string;

	}

	public static function bell($count = 1) {
		echo str_repeat("\007", $count);
	}

}