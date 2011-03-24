<?php
/**
 * DB testing tool - validating db schema defs / data files (sql) vs. import/export procedures
 * More docs in dbaimpexp.php
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2011
 * @license code licensed under the GNU GPL 2.0: see README
 *
 * @todo add two-pass support
 * @todo add comparison of dumped sql with source sql
 */

// poor man's workaround for not setting up prperly include path
if ( !(@include 'extension/ezdbintegrity/classes/clitools.php') && !(@include 'classes/clitools.php') ) require( 'clitools.php' );
if ( !(@include 'extension/ezdbintegrity/classes/dbtools.php') && !(@include 'classes/dbtools.php') ) require( 'dbtools.php' );

$options = CLITools::getOptions(
    array(
        // account used to connect to db and create/drop new users/schemas
        'dbtype' => 'mysql',
        'root-user' => 'root',
        'root-password' => '',
        'root-host' => 'localhost',
        // for oracle: password and tablespace used for new users/schemas
        'user-password' => 'publish',
        'user-tablespace' => 'USERS',
        // path to executables
        'php' => 'php',
        'mysql' => 'mysql',
        'sqlplus' => 'sqlplus',
        // When true, the .dba schema file will be imported and exported two times.
        // This should always be done for oracle and postgres, as the 1st export will be
        // different from the original schema files
        'twopasses' => true,
        // when true, will remove all schemas/dtabases from db server after execution
        'cleanup' => false,
        // if not empty, the script will only scan these extensions. For kernel, an empty string is used
        'extensionslist' => array(),
        // List of ezpublish directories to scan. Array key will be used as name prefix
        // in building a new schema/database in the db server
        'ezversions' => array(
            'current' => getcwd()
        ),
        // a config file that can be used as shorthand for options (php syntax, needs to add stuff to $options array)
        'config-file' => '',
    ) );

if ( $options['config-file'] != '' )
{
    if ( is_file( $options['config-file'] ) )
    {
        include $options['config-file'];
    }
    else
    {
        echo "WARNING!!! config file {$options['config-file']} not found\n";
    }
}

CLITools::dumpOptions( $options );
DBTestRunner::run( $options );

class DBTestRunner
{
    /** main loop **/
    static function run( $options )
    {
        foreach ( $options['ezversions'] as $version => $dir )
        {
            echo "Testing eZP version $version in dir $dir\n";
            chdir( $dir ) or die( "Dir $dir not found" );

            //scan extensions for dba files, putting kernel dir on top
            if ( $options['dbtype'] != 'oracle' )
            {
                $extensions = array( '' => 'kernel/sql/'.$GLOBALS['dbtype'].'/kernel_schema.sql' );
            }

            foreach( scandir( "$dir/extension" ) as $ext )
            {
                if ( $ext != '..' )
                {
                    if ( is_file( "$dir/extension/$ext/sql/" . $options['dbtype'] . "/schema.sql" ) )
                    {
                        $extensions[$ext] = "$dir/extension/$ext/sql/" . $options['dbtype'] . "/schema.sql";
                    }
                    elseif ( is_file( "$dir/extension/$ext/sql/" . $options['dbtype'] . "/" . $options['dbtype'] . ".sql" ) )
                    {
                        $extensions[$ext] = "$dir/extension/$ext/sql/" . $options['dbtype'] . "/" . $options['dbtype'] . ".sql";
                    }
                }
            }

            // use a unique name for the new schemas in the db
            $baseschema = $version . '_sqlclean';

            /// Load existing SQL file.
            foreach( $extensions as $name => $file )
            {
                if ( count( $options['extensionslist'] ) == 0 || in_array( $name, $options['extensionslist']) )
                {
                    echo "Found sql file in extension [$name]\n";

                    $ext = substr( $name, 0, 9 ); // arbitrary number to stay below 30 chars with the generated schema name
                    if ( $ext != '' )
                    {
                        $ext = "_$ext";
                    }
                    $schema = $baseschema . $ext;

                    echo "1st pass sql test (schema)\n";

                    $out1 = $file . '.' . $options['dbtype'];
                    //$out1d = $datafile . '.' . $GLOBALS['dbtype'];
                    DBTools::importAndDumpSQLFile( $options, $file, $schema, $out1 );

                    /*// only check if 1st pass output is different from original for mysql
                    if ( $GLOBALS['dbtype'] == 'mysql' && filesize( $schemafile ) != filesize( $out1 . '.dba' ) )
                    {
                        echo "WARNING!!! schema files generated on 1st pass diverge from original\n";
                    }

                    if ( file_exists( $datafile ) )
                    {
                        echo "1st pass dba test (data)\n";
                        DBTools::importAndDumpDataFile( $datafile, $schemafile, $schema, $out1d );

                        if ( $GLOBALS['dbtype'] == 'mysql' && filesize( $datafile ) != filesize( $out1d . '.dba' ) )
                        {
                            echo "WARNING!!! data files generated on 1st pass diverge from original\n";
                        }
                    }

                    if ( $GLOBALS['twopasses'] )
                    {
                        echo "2nd pass dba test (schema)\n";
                        $out2 = $out1 . '_bis';
                        DBTools::importAndDumpSchemaFile( $out1 . '.dba', $schema . '_bis', $out2 );

                        if ( filesize( $out1 . '.dba' ) != filesize( $out2 . '.dba' ) )
                        {
                            echo "WARNING!!! schema files generated on 1st and 2nd pass diverge\n";
                        }

                        if ( file_exists( $datafile ) )
                        {
                            echo "2nd pass dba test (data)\n";
                            $out2d = $out1d . '_bis';
                            DBTools::importAndDumpDataFile( $out1d . '.dba', $out1 . '.dba', $schema . '_bis', $out2d );

                            if ( filesize( $out1d . '.dba' ) != filesize( $out2d . '.dba' ) )
                            {
                                echo "WARNING!!! data files generated on 1st and 2nd pass diverge\n";
                            }
                        }
                    }*/
                }
            }
        }
    }

}

?>