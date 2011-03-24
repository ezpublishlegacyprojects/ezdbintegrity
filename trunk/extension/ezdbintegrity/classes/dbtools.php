<?php
/**
 * Functionality to import/export data and structures from databases
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2011
 * @license code licensed under the GNU GPL 2.0: see README
 *
 * @todo add postgres support
 */

class DBTools
{

    static function importSQLFile( $file, $schema, $options )
    {
        switch( $options['dbtype'] )
        {
            case 'oracle':
                $user = $schema;
            	$password = $options['user-password'];
                $schema = $options['root-host'];
                $cmd = 'echo exit | ' . $options['sqlplus'] . " $user/$password@$schema @$file";
                break;
            case 'postgres':
            case 'postgresql':
            	die( 'Postgres not supported yet' );
			default:
                $user = $options['root-user'];
                $password = $options['root-password'];
                $cmd = $options['mysql'] . " -h{$options['root-host']} -u$user -p$password $schema < $file";
        }
        //echo "$cmd\n";
        /// @todo test for errors
        $result = exec ( $cmd, $results );
    }

    static function importDBAFile( $file, $schema, $options, $extraoptions='' )
    {
        switch( $options['dbtype'] )
        {
            case 'oracle':
                $user = $schema;
                $schema = $options['root-host'];
            	$password = $options['user-password'];
                break;
            case 'postgres':
            case 'postgresql':
            	die( 'Postgres not supported yet' );
            default:
                $user = $options['root-user'];
                $password = $options['root-password'];
        }
        $cmd = $options['php'] . ' bin/php/ezsqlinsertschema.php --type=' . $options['dbtype'] . ' --user=' . $user . ' --host=' . $options['root-host']  . ' --password=' . $password . " $extraoptions $file $schema";
        //echo "$cmd\n";
        /// @todo test for errors
        $result = exec ( $cmd, $results );
    }

    static function dumpDBAFile( $file, $schema, $type,  $options, $extraoptions=' --no-sort' )
    {
        switch( $options['dbtype'] )
        {
            case 'oracle':
                $user = $schema;
                $schema = $options['root-host'];
            	$password = $options['user-password'];
                break;
            default:
                $user = $options['root-user'];
                $password = $options['root-password'];
        }
        if ( $type == '' )
        {
            $type = 'array';
        }
        $type = ' --output-' . $type;
        is_file( $file ) && unlink( $file );
        $cmd = $options['php'] . ' bin/php/ezsqldumpschema.php --type=' . $options['dbtype'] . ' --user=' . $user . ' --host=' . $options['root-host']  . ' --password=' . $password . " $extraoptions $type $schema $file";
        //echo "$cmd\n";
        /// @todo test for errors
        $result = exec ( $cmd, $results );
    }

    static function importAndDumpSchemaFile( $options, $infile, $schemaname, $outfile='' )
    {
        is_file( $infile ) or die( "cannot insert dba file $infile: file not found" );
        echo "create schema $schemaname\n";
        self::dropSchema( $schemaname, $options );
        self::createSchema( $schemaname, $options );
        echo "import structure from file $infile\n";
        self::importDBAFile( $infile, $schemaname, $options );
        echo "export structure\n";
        if ( $outfile == '' )
        {
            $outfile = $infile . '.out';
        }
        self::dumpDBAFile( $outfile . '.dba', $schemaname, 'array', $options );
        self::dumpDBAFile( $outfile . '.sql', $schemaname, 'sql', $options );
        /// @bug we should not clean up here if we do data testing later in same schema
        if ( $options['cleanup'] )
        {
            self::dropSchema( $schemaname, $options );
        }
    }

    static function importAndDumpSQLFile( $options, $infile, $schemaname, $outfile='' )
    {
        is_file( $infile ) or die( "cannot insert sql file $infile: file not found" );
        echo "create schema $schemaname\n";
        self::dropSchema( $schemaname, $options );
        self::createSchema( $schemaname, $options );
        echo "import from file $infile\n";
        self::importSQLFile( $infile, $schemaname, $options );
        echo "export structure\n";
        if ( $outfile == '' )
        {
            $outfile = $infile . '.out';
        }
        self::dumpDBAFile( $outfile . '.dba', $schemaname, 'array', $options );
        self::dumpDBAFile( $outfile . '.sql', $schemaname, 'sql', $options );
        /// @bug we should not clean up here if we do data testing later in same schema
        if ( $options['cleanup'] )
        {
            self::dropSchema( $schemaname, $options );
        }
    }

    static function importAndDumpDataFile( $options, $infile, $schemaname, $schemafile, $outfile='' )
    {
        is_file( $infile ) or die( "cannot insert dba file $infile: file not found" );
        echo "import data from file $infile\n";
        self::importDBAFile( $infile, $schemaname, $options, " --insert-types=data --schema-file=$schemafile" );
        echo "export data\n";
        if ( $outfile == '' )
        {
            $outfile = $infile . '.out';
        }
        self::dumpDBAFile( $outfile . '.dba', $schemaname, 'array', $options, ' --output-types=data' );
        self::dumpDBAFile( $outfile . '.sql', $schemaname, 'sql', $options, ' --output-types=data' );
        if ( $options['cleanup'] )
        {
            self::dropSchema( $schemaname, $options );
        }
    }

	/**
    * @todo return exit code
    */
    static function createSchema( $name, $options )
    {
        switch( $options['dbtype'] )
        {
            case 'oracle':
                $conn = oci_connect( $options['root-user'], $options['root-password'], $options['root-host'] ) or die( 'Could not connect to db as root' );
                $stid = oci_parse( $conn, "CREATE USER $name IDENTIFIED BY {$options['user-password']} DEFAULT TABLESPACE {$options['user-tablespace']} QUOTA UNLIMITED ON {$options['user-tablespace']}" );
                oci_execute($stid);
                $stid = oci_parse( $conn, "GRANT CREATE SESSION TO $name" );
                oci_execute($stid);
                $stid = oci_parse( $conn, "GRANT CREATE TABLE TO $name" );
                oci_execute($stid);
                $stid = oci_parse( $conn, "GRANT CREATE TRIGGER TO $name" );
                oci_execute($stid);
                $stid = oci_parse( $conn, "GRANT CREATE SEQUENCE TO $name" );
                oci_execute($stid);
                $stid = oci_parse( $conn, "GRANT CREATE PROCEDURE TO $name" );
                oci_execute($stid);
                break;
            case 'postgres':
            case 'postgresql':
            	die( 'Postgres not supported yet' );
            default:
                $conn = mysqli_connect( $options['root-host'], $options['root-user'], $options['root-password'] ) or die( 'Could not connect to db as root' );
                $res = mysqli_query( $conn, "CREATE DATABASE $name character set utf8" );
        }
    }

    /**
     * Does not echo warning if schema does not exist
     * @bug mysql does not seem to be dropping the schema correctly...
     * @todo return exit code
     */
    static function dropSchema( $name, $options )
    {
        switch( $options['dbtype'] )
        {
            case 'oracle':
                $conn = oci_connect( $options['root-user'], $options['root-password'], $options['root-host'] ) or die( 'Could not connect to db as root' );
                $stid = oci_parse( $conn, "DROP USER $name CASCADE" );
                @oci_execute( $stid );
                break;
            case 'postgres':
            case 'postgresql':
            	die( 'Postgres not supported yet' );
            default:
                $conn = mysqli_connect( $options['root-host'], $options['root-user'], $options['root-password'] ) or die( 'Could not connect to db as root' );
                $res = mysqli_query( $conn, "DROP DATABASE $name IF EXISTS" );
        }
    }

}
?>