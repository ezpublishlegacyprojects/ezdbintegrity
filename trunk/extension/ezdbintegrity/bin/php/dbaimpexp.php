<?php
/**
 * DB testing tool - validating db schema defs / data files (dba) vs. import/export procedures
 *
 * 1.for all .dba files in an ezp install, imports them in a db (creating a new
 *   schema/db for each .dba file), then exports them again to compare the newly
 *   exported file with the original one.
 *   For both schema files and data files, we export twice, in .dba and .sql format.
 *
 *   NB: to correctly scan extensions that contain .dba files, those extensions
 *   have to be activated
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2011
 * @license code licensed under the GNU GPL 2.0: see README
 *
 * @todo test extensions that contain = .dba files with wrong names/paths
 * @todo diff obtained files by content rather than by filesize only
 *
 */

// poor man's workaround for not setting up prperly include path
if ( !(@include 'extension/ezdbintegrity/classes/clitools.php') && !(@include 'classes/clitools.php') ) require( 'clitools.php' );
if ( !(@include 'extension/ezdbintegrity/classes/dbtools.php') && !(@include 'classes/dbtools.php') ) require( 'dbtools.php' );

$options = CLITools::getOptions(
    array(

        'dbtype' => 'mysql',
        'root-user' => 'root',
        'root-password' => '',
        'root-host' => 'localhost',
        // path to php executable
        'php' => 'php',
        // for oracle: password and tablespace used for new users/schemas
        'user-password' => 'publish',
        'user-tablespace' => 'USERS',
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
            $extensions = array( '' => '' );
            foreach( scandir( "$dir/extension" ) as $ext )
            {
                if ( $ext != '..' && is_file( "$dir/extension/" . $ext . "/share/db_schema.dba" ) )
                {
                    $extensions[$ext] = "extension/$ext/";
                }
            }

            // use a unique name for the new schemas in the db
            $baseschema = $version . '_dbaclean';

            /// Load existing DBA file, and dump it again, to see if they match.
            /// Then load the new dumped .dba file, and dump it a second time
            foreach( $extensions as $name => $directory )
            {
                if ( count( $options['extensionslist'] ) == 0 || in_array( $name, $options['extensionslist']) )
                {
                    echo "Found dba file in extension [$name]\n";

                    /// @todo if extension $name is not enabled, we will get invalid results,
                    ///       as there might be some data in the extensions' dbschema.ini file

                    $schemafile = $directory . "share/db_schema.dba";
                    $datafile = $directory . "share/db_data.dba";
                    $ext = substr( $name, 0, 9 ); // arbitrary number to stay below 30 chars with the generated schema name
                    if ( $ext != '' )
                    {
                        $ext = "_$ext";
                    }
                    $schema = $baseschema . $ext;

                    echo "1st pass dba test (schema)\n";
                    $out1 = $schemafile . '.' . $options['dbtype'];
                    $out1d = $datafile . '.' . $options['dbtype'];
                    DBTools::importAndDumpSchemaFile( $options, $schemafile, $schema, $out1 );

                    // only check if 1st pass output is different from original for mysql
                    if ( $options['dbtype'] == 'mysql' && filesize( $schemafile ) != filesize( $out1 . '.dba' ) )
                    {
                        echo "WARNING!!! schema files generated on 1st pass diverge from original\n";
                    }

                    if ( file_exists( $datafile ) )
                    {
                        echo "1st pass dba test (data)\n";
                        DBTools::importAndDumpDataFile( $options, $datafile, $schema, $schemafile, $out1d );

                        if ( $options['dbtype'] == 'mysql' && filesize( $datafile ) != filesize( $out1d . '.dba' ) )
                        {
                            echo "WARNING!!! data files generated on 1st pass diverge from original\n";
                        }
                    }

                    if ( $options['twopasses'] )
                    {
                        echo "2nd pass dba test (schema)\n";
                        $out2 = $out1 . '_bis';
                        DBTools::importAndDumpSchemaFile( $options, $out1 . '.dba', $schema . '_bis', $out2 );

                        if ( filesize( $out1 . '.dba' ) != filesize( $out2 . '.dba' ) )
                        {
                            echo "WARNING!!! schema files generated on 1st and 2nd pass diverge\n";
                        }

                        if ( file_exists( $datafile ) )
                        {
                            echo "2nd pass dba test (data)\n";
                            $out2d = $out1d . '_bis';
                            DBTools::importAndDumpDataFile( $options, $out1d . '.dba', $schema . '_bis', $out1 . '.dba', $out2d );

                            if ( filesize( $out1d . '.dba' ) != filesize( $out2d . '.dba' ) )
                            {
                                echo "WARNING!!! data files generated on 1st and 2nd pass diverge\n";
                            }
                        }
                    }
                }
            }
        }
    }

}

?>