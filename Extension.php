<?php
/**
 * This file is part of BoltClassManager Bolt CMS Extension.
 * 
 * BoltClassManager Bolt CMS Extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * BoltClassManager Bolt CMS Extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see 
 * <http://www.gnu.org/licenses/>.
 *
 * @author Johnathan Pulos <johnathan@missionaldigerati.org>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * 
 */

namespace Bolt\Extension\MissionalDigerati\BoltClassManager;

use Symfony\Component\HttpFoundation\Response;
use Bolt\Translation\Translator as Trans;

/**
 * BoltClassManager is an Extension for the Bolt CMS (@link http://bolt.cm)
 *
 * @package default
 * @author Johnathan Pulos
 **/
class Extension extends \Bolt\BaseExtension
{
    /**
     * Array of extension pathes
     *
     * @var array
     * @access private
     **/
    private $extensionPaths = array(
        'home'              =>  '',     
        'roster'            =>  '',
        'sign_in'           =>  '',
        'branding'          =>  '',
        'remove_student'    =>  ''
    );

    /**
     * Get the name of the extension/
     *
     * @return string
     * @author Johnathan Pulos
     **/
    public function getName()
    {
        return "Bolt Class Manager";
    }

    /**
     * initialize the extension
     *
     * @return void
     * @author Johnathan Pulos
     **/
    public function initialize()
    {
        $this->config = $this->getConfig();
        $this->authorized = false;
        $this->extensionPaths['branding'] = $this->app['config']->get('general/branding/path');

        /**
         * ensure proper config
         */
        if (!isset($this->config['permissions']) || !is_array($this->config['permissions'])) {
            $this->config['permissions'] = array('root', 'admin', 'developer');
        } else {
            $this->config['permissions'][] = 'root';
        }

        // check if user has allowed role(s)
        $currentUser    = $this->app['users']->getCurrentUser();
        $currentUserId  = $currentUser['id'];

        foreach ($this->config['permissions'] as $role) {
            if ($this->app['users']->hasRole($currentUserId, $role)) {
                $this->authorized = true;
                break;
            }
        }

        if ($this->authorized) {
            /**
             * Add the button in the extensions navigation
             */
            $this->path = $this->extensionPaths['branding'] . '/extensions/class-manager';
            $this->extensionPaths['home'] = $this->path;
            $this->app->match($this->path, array($this, 'classManagerLoad'));
            $this->addMenuOption(Trans::__('Class Manager'), $this->app['paths']['bolt'] . 'extensions/class-manager', "icon-group");
        }
        /**
         * Add the clickable links for this page
         */
        $this->extensionPaths['roster'] = $this->extensionPaths['branding'] . '/extensions/class-manager/roster/';
        $this->app->match($this->extensionPaths['roster'] . "{class_slug}/{id}", array($this, 'classManagerLoadRoster'));
        $this->extensionPaths['sign_in'] = $this->extensionPaths['branding'] . '/extensions/class-manager/sign-in-sheet/';
        $this->app->match($this->extensionPaths['sign_in'] . "{class_slug}/{id}", array($this, 'classManagerLoadSignInSheet'));
        /**
         * /class-manager/class/digerati_101/3/remove-student/1
         */
        $this->extensionPaths['remove_student'] = $this->extensionPaths['branding'] . '/extensions/class-manager/class/';
        $this->app->match($this->extensionPaths['remove_student'] . "{class_slug}/{id}/remove-student/{student_id}", array($this, 'classManagerRemoveStudent'));

        /**
         * Add ClassManager template namespace to twig
         */
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/views/', 'ClassManager');
    }

    /**
     * Load the ClassManager Response
     *
     * @return Response \Symfony\Component\HttpFoundation\JsonResponse
     * @access public
     * @author Johnathan Pulos
     **/
    public function classManagerLoad()
    {
        $classes = $this->app['storage']->getContent('upcoming_classes', array('order' => 'date_of_class DESC'));
        $deleteSuccess = $this->app['request']->get('delete_success');

        $body = $this->app['render']->render('@ClassManager/base.twig',
            array(
                'classes'           =>  $classes,
                'roster_path'       =>  $this->extensionPaths['roster'],
                'delete_success'    =>  $deleteSuccess,
                'sign_in_path'      =>  $this->extensionPaths['sign_in']
            )
        );

        return new Response($this->injectAssets($body));
    }

    /**
     * Load the Roster Page for the class
     *
     * @param string $class_slug The slug for the given class
     * @param integer $id the id of the class to retrieve
     * @return Response \Symfony\Component\HttpFoundation\JsonResponse
     * @access public
     * @author Johnathan Pulos
     **/
    public function classManagerLoadRoster($class_slug = '', $id = null)
    {
        $id = (int) $id;
        $view = $this->getViewTemplate($class_slug);
        $table = $this->getClassTable($class_slug);
        $deleteSuccess = $this->app['request']->get('delete_success');

        $sql = "Select * FROM $table WHERE class_id = ? ORDER BY name ASC";
        $students = $this->app['db']->fetchAll($sql, array($id));

        foreach ($students as $key => $value) {
            $students[$key]['phone'] = $this->formatPhoneNumber($students[$key]['phone']);
        }

        $classRecord = $this->app['storage']->getContent('upcoming_classes', array('id' => $id, 'returnsingle' => true));

        $body = $this->app['render']->render("@ClassManager/$view",
            array(
                'students'              =>  $students,
                'class_record'          =>  $classRecord,
                'is_sign_in'            =>  false,
                'training_type_slug'    =>  $class_slug,
                'class_id'              =>  $id,
                'delete_success'        =>  $deleteSuccess,
                'remove_student_path'   =>  $this->extensionPaths['remove_student'],
                'home_path'             =>  $this->extensionPaths['home']
            )
        );

        return new Response($this->injectAssets($body));
    }

    /**
     * Load the Sign In Sheet
     *
     * @param string $class_slug The slug for the given class
     * @param integer $id the id of the class to retrieve
     * @return Response \Symfony\Component\HttpFoundation\JsonResponse
     * @access public
     * @author Johnathan Pulos
     **/
    public function classManagerLoadSignInSheet($class_slug = '', $id = null)
    {
        $id = (int) $id;
        $view = $this->getViewTemplate($class_slug);
        $table = $this->getClassTable($class_slug);
        $deleteSuccess = $this->app['request']->get('delete_success');
        
        $sql = "Select * FROM $table WHERE class_id = ? ORDER BY name ASC";
        $students = $this->app['db']->fetchAll($sql, array($id));

        foreach ($students as $key => $value) {
            $students[$key]['phone'] = $this->formatPhoneNumber($students[$key]['phone']);
        }

        $classRecord = $this->app['storage']->getContent('upcoming_classes', array('id' => $id, 'returnsingle' => true));

        $body = $this->app['render']->render("@ClassManager/$view",
            array(
                'students'              =>  $students,
                'class_record'          =>  $classRecord,
                'training_type_slug'    =>  $class_slug,
                'class_id'              =>  $id,
                'is_sign_in'            =>  true,
                'delete_success'        =>  $deleteSuccess,
                'remove_student_path'   =>  $this->extensionPaths['remove_student'],
                'home_path'             =>  $this->extensionPaths['home']
            )
        );

        return new Response($body);
    }

    /**
     * Remove a student from the class
     *
     * @param string $class_slug The slug for the given class
     * @param integer $id the id of the class
     * @param integer $id the id of the student to remove
     * @return redirect
     * @access public
     * @author Johnathan Pulos
     **/
    public function classManagerRemoveStudent($class_slug = '', $id = null, $student_id = null)
    {
        $redirectPage = $this->app['request']->get('redirect');
        $shouldRedirect = false;
        $success = false;
        if ($this->app['request']->getMethod() == 'POST') {
            $method = $this->app['request']->get('_method');
            if ($method != 'DELETE') {
                $shouldRedirect = true;
            }
        } else {
            $shouldRedirect = true;
        }
        if ($shouldRedirect === false) {
            /**
             * Remove the student, and send them back with a message
             */
            $id = (int) $id;
            $student_id = (int) $student_id;
            $view = $this->getViewTemplate($class_slug);
            $table = $this->getClassTable($class_slug);
            
            $sql = "DELETE FROM $table WHERE class_id = ? AND id = ?";
            $deleteQuery = $this->app['db']->executeQuery(
                $sql,
                array($id, $student_id),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT)
            );
            $success = true;
        }
        if ((isset($redirectPage) && ($redirectPage != ''))) {
            $redirectTo = $this->extensionPaths[$redirectPage] . $class_slug . "/" . $id;
        } else {
            $redirectTo = $this->extensionPaths['home'];
        }
        if ($success === true) {
            $redirectTo = $redirectTo . "?delete_success=true";
        }
        return $this->app->redirect($redirectTo);
    }

    /**
     * Formats a phone number for better display.
     *
     * @link http://stackoverflow.com/a/6604465
     *
     * @param string $number The number to format.
     * @return string The proper formatted number.
     * @access private
     * @author Johnathan Pulos
     **/
    private function formatPhoneNumber($number)
    {
        $cleanedNumber = preg_replace("/[^0-9]/", "", $number);
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $cleanedNumber);
    }

    /**
     * Injects the assets into the head tag
     *
     * @param string $html the HTML to replace
     * @return mixed
     * @access private
     * @author Johnathan Pulos
     **/
    private function injectAssets($html)
    {
        $urlbase = $this->app['paths']['app'];

        $assets = '<link rel="stylesheet" href="{urlbase}/extensions/ClassManager/assets/class_manager_styles.css">';
        $assets .= '<script src="{urlbase}/extensions/ClassManager/assets/stupidtable.min.js"></script>';
        $assets .= '<script src="{urlbase}/extensions/ClassManager/assets/class_manager_scripts.js"></script>';

        $assets = preg_replace('~\{urlbase\}~', $urlbase, $assets);

        // Insert just before </head>
        preg_match("~^([ \t]*)</head~mi", $html, $matches);
        $replacement = sprintf("%s\t%s\n%s", $matches[1], $assets, $matches[0]);
        return $this->strReplaceFirst($matches[0], $replacement, $html);
    }

    /**
     * Retrieve the name of the SQL table based on the given slug
     * 
     * @param string $slug the slug of the training type
     * @return string The SQL table to retrieve data from
     * @access private
     * @author Johnathan Pulos
     **/
    private function getClassTable($slug)
    {
        return ($slug == 'digerati_101') ? 'form_register_digerati_101' : 'form_register_faith_tech';
    }
    /**
     * Retrieve the view template to use
     * 
     * @param string $slug the slug of the training type
     * @return string The view template to use
     * @access private
     * @author Johnathan Pulos
     **/
    private function getViewTemplate($slug)
    {
        return ($slug == 'digerati_101') ? 'roster_digerati.twig' : 'roster_faith_tech.twig';
    }
    /**
     * Replace the first in a string
     * @param string $search what to search for
     * @param string $replace what to replace
     * @param string $subject what to replace it with
     * @return mixed
     *
     */
    private function strReplaceFirst($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }

}
