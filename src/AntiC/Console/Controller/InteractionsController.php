<?php

namespace AntiC\Console\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InteractionsController
{
    /**
     * Shows the list of interactions
     *
     * @route /console/interactions
     * @param Application
     * @return twig render IF authenticated, redirect to login otherwise.
     */
    public function indexAction(Application $app)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        require_once 'api/get/listEnzymes.php';
        $enzymeList = getEnzymeList();

        $enzymes = array();
        foreach ($enzymeList as $enzyme) {
            $enzymes[] = array(
                "name" => $enzyme['name'],
                "id" => $enzyme['name'],
                "enabled" => $enzyme['deleted'],
            );
        }

        return $app['twig']->render('interactions/index.html.twig', array(
            'interactions' => $enzymes,
        ));
    }

    /**
     * Shows and processes add interactions form.
     *
     * @route /console/interactions/add
     * @param Application
     * @param Request
     * @return twig render IF authenticated, redirect to login otherwise.
     */
    public function addAction(Application $app, Request $request)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        if ($request->isMethod('POST')) {
            $interactions = array();
            $interactions['name'] = $request->get('name');

            $substrates = array();
            foreach($request->get('substrate') as $substrate)
            {
                if (empty($substrate["risk"]) || empty($substrate["name"])) continue;
                $arrayOfValues = array("compound" => $substrate['name'], "severity" => $substrate['risk']);
                $substrates[] = $arrayOfValues;
            }

            $inhibitors = array();
            foreach($request->get('inhibitor') as $inhibitor)
            {
                if (empty($inhibitor["risk"]) || empty($inhibitor["name"])) continue;
                $arrayOfValues = array("compound" => $inhibitor['name'], "severity" => $inhibitor['risk']);
                $inhibitors[] = $arrayOfValues;
            }

            $inducers = array();
            foreach($request->get('inducer') as $inducer)
            {
                if (empty($inducer["risk"]) || empty($inducer["name"])) continue;
                $arrayOfValues = array("compound" => $inducer['name'], "severity" => $inducer['risk']);
                $inducers[] = $arrayOfValues;
            }

            $interactions["Substrate"] = $substrates;
            $interactions["Inhibitor"] = $inhibitors;
            $interactions["Inducer"] = $inducers;

            require 'api/put/putEnzyme.php';
            if (insertEnzyme($interactions, $app['user']->getName())) {
                $app['session']->getFlashBag()->set('success', "Successfully added Interaction: ".$interactions['name']);
                return $app->redirect($app['url_generator']->generate('console.interactions.edit', array('ID' => $interactions['name'])));
            } else {
                $app['session']->getFlashBag()->set('failure', "An error occured. Please try again.");
            }
        }

        return $app['twig']->render('interactions/add.html.twig');
    }

    /**
     * Shows and processes edit interactions form.
     *
     * @route /console/interactions/{ID}
     * @param Application
     * @param Request
     * @return twig render IF authenticated, redirect to login otherwise.
     */
    public function editAction(Application $app, Request $request)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        require 'api/dbConnect/connectStart.php';
        require 'api/get/getEnzyme.php';
        require 'api/post/postEnzyme.php';

        if ($request->isMethod('POST')) {
            $orig_name = $request->get('orig_name');
            $currentEnzyme = getEnzyme($orig_name, $dbhandle);

            // Name of Drug to Update
            $name = $orig_name;
            if ($orig_name != $request->get('name')) 
                $name = $request->get('name');

            $update["name"] = $name;

            // Flat Array structuring of POSTED data
            $substrateList = array();
            foreach ($request->get('substrate') AS $substrate) {
                if (empty($substrate["name"]) || empty($substrate["risk"])) continue;
                $substrateList[$substrate["name"]] = $substrate["risk"];
            }

            $inhibitorList = array();
            foreach ($request->get('inhibitor') AS $inhibitor) {
                if (empty($inhibitor["name"]) || empty($inhibitor["risk"])) continue;
                $inhibitorList[$inhibitor["name"]] = $substrate["risk"];
            }

            $inducerList = array();
            foreach ($request->get('inducer') AS $inducer) {
                if (empty($inducer["name"]) || empty($inducer["risk"])) continue;
                $inducerList[$inducer["name"]] = $inducer["risk"];
            }


            // Flat Array structuring of DB data
            $dbSubstrateList = array();
            foreach ($currentEnzyme['Substrate'] AS $substrate) {
                $dbSubstrateList[$substrate["compound"]] = $substrate["severity"];
            }

            $dbInhibitorList = array();
            foreach ($currentEnzyme['Inhibitor'] AS $inhibitor) {
                $dbInhibitorList[$inhibitor["compound"]] = $substrate["severity"];
            }

            $dbInducerList = array();
            foreach ($currentEnzyme['Inducer'] AS $inducer) {
                $dbInducerList[$inducer["compound"]] = $inducer["severity"];
            }

            
            $addSubstrateUpdate = array_diff_assoc($substrateList, $dbSubstrateList);
            $addInhibitorUpdate = array_diff_assoc($inhibitorList, $dbInhibitorList);
            $addInducerUpdate = array_diff_assoc($inducerList, $dbInducerList);

            $removeSubstrateUpdate = array_diff_assoc($dbSubstrateList, $substrateList);
            $removeInhibitorUpdate = array_diff_assoc($dbInhibitorList, $inhibitorList);
            $removeInducerUpdate = array_diff_assoc($dbInducerList, $inducerList);

            // Create Updates Array
            foreach ($removeSubstrateUpdate AS $compound => $risk) {
                $update["substrate"]["values"][] = array("compound" => $compound);
                $update["substrate"]["options"][] = array("status" => "deleted", "compound" => $compound, "interaction" => "Substrate");
            }
            foreach ($addSubstrateUpdate AS $compound => $risk) {
                $update["substrate"]["values"][] = array("enzyme" => $name, "compound" => $compound, "severity" => $risk, "interaction" => "Substrate");
                $update["substrate"]["options"][] = array("status" => "added");
            }

            foreach ($removeInhibitorUpdate AS $compound => $risk) {
                $update["inhibitor"]["values"][] = array("compound" => $compound);
                $update["inhibitor"]["options"][] = array("status" => "deleted", "compound" => $compound, "interaction" => "Inhibitor");   
            }
            foreach ($addInhibitorUpdate AS $compound => $risk) {
                $update["inhibitor"]["values"][] = array("enzyme" => $name, "compound" => $compound, "severity" => $risk, "interaction" => "Inhibitor");
                $update["inhibitor"]["options"][] = array("status" => "added");   
            }

            foreach ($removeInducerUpdate AS $compound => $risk) {
                $update["inducer"]["values"][] = array("compound" => $compound);
                $update["inducer"]["options"][] = array("status" => "deleted", "compound" => $compound, "interaction" => "Inducer");   
            }
            foreach ($addInducerUpdate AS $compound => $risk) {
                $update["inducer"]["values"][] = array("enzyme" => $name, "compound" => $compound, "severity" => $risk, "interaction" => "Inducer");
                $update["inducer"]["options"][] = array("status" => "added");   
            }

            error_log(print_r($update, true));

            if (updateEnzyme($update, $app['user']->getName(), $orig_name, $dbhandle)) {
                $app['session']->getFlashBag()->set('success', "Successfully edited Interaction: ".$name);
                if ($name != $orig_name)
                    return $app->redirect($app['url_generator']->generate('console.interactions.edit', array('ID' => $name)));
            } else {
                $app['session']->getFlashBag()->set('failure', "An error occured. Please try again.");
            }
        }

        
        $enzyme = getEnzyme($request->get('ID'), $dbhandle);

        $who_updated = $enzyme["who_updated"];

        // Query Database with ID and Return Interactions Name and Information to Twig
        return $app['twig']->render('interactions/edit.html.twig', array(
            'interaction' => $enzyme,
            'who_updated' => $who_updated
        ));
    }

    /**
     * Calls API to Show and Hide Drug
     *
     * @route /console/interactions/{ID}/showhide
     * @param Application
     * @param Request
     * @return 1 or 0 depending on results of DB call, redirect to login otherwise
     */
    public function showHideAction(Application $app, Request $request)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        if ($request->isMethod('POST')) {
            require 'api/delete/deleteEnzyme.php';
            $id = $app['user']->getName();
            $enzymeId = $request->get('ID');
            $showHide = $request->get('enabled');
            $response = deleteEnzyme($enzymeId, $id, $showHide);
            if ($response) {
                $response = "1";
            } else {
                $response = "Error: Something went wrong";
            }
        } else {
            $response = "Error: Not a valid Request";
        }

        return new Response($response);
    }

}