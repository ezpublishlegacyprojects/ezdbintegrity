<?php
/**
 * Cli options management without relying on eZP/ZC code
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2011
 * @license code licensed under the GNU GPL 2.0: see README
 */

class CLITools
{
    static function getOptions( $defaults=array(), $helptext='' )
    {
        global $argv;

        if ( in_array( @$argv[1], array( '--help', '-help', '-h', '-?' ) ) )
        {
            if ( $helptext != '' )
            {
                echo $helptext;
            }
            echo "Options:\n";
            foreach ( $defaults as $key => $val )
            {
                echo "  --$key (defaults to " . json_encode( $val ) . ")\n";
            }
            echo "(json syntax allowed for array values)";
            exit( 0 );
        }

        array_shift( $argv );
        foreach ( $argv as $arg )
        {
            if ( strpos( $arg, '=' ) != false )
            {
                $arg = preg_replace( '/^--/', '', $arg );
                $args = explode( '=', $arg, 2 );
                /// support json syntax for arrays, true, false, null
                if ( preg_match( '/(^".*"$)|(^\[".*\]$)|(^\{".*\}$)|(^true$)|(^false$)|(^null$)/', $args[1] ) )
                {
                    $args[1] = json_decode( $args[1], true );
                }
                $defaults[$args[0]] = $args[1];
            }
        }
        return $defaults;
    }

    static function dumpOptions( $options, $text='' )
    {
        if ( $text != '' )
        {
            echo $text;
        }
        echo "Using options:\n";
        foreach ( $options as $key => $val )
        {
            echo "  $key => " . json_encode( $val ) . "\n";
        }
        echo "\n";
    }
}

?>