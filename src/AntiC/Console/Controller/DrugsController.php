<?php

namespace AntiC\Console\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AntiC\User\Manager\UserManager;

class DrugsController
{
    /**
     * Shows list of Drugs. Initial screen on login.
     * 
     * @route /console
     * @param Application
     * @return twig render IF authenticated, redirects to login otherwise.
     */
    public function indexAction(Application $app)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }
        if (!$app['user']->getEnabled()) {
            $app['security']->setToken(null);
            $app['session']->getFlashBag()->set('notice', "Your account has been disabled. Please contact site support.");
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        require_once 'api/get/listDrugs.php';
        $drugsList = getDrugList();

        $drugs = array();
        foreach ($drugsList as $drug) {
            $drugs[] = array(
                "commonName" => $drug['g_name'],
                "tradeName" => $drug['t_name'],
                "id" => $drug['g_name'],
                "enabled" => $drug['deleted'],
            );
        }

        return $app['twig']->render('drugs/index.html.twig', array(
            'drugs' => $drugs
        ));
    }

    /**
     * Shows and processes add drug form.
     *
     * @route /console/drugs/add
     * @param Application
     * @param Request
     * @return twig render IF authenticated, redirects to login otherwise.
     */
    public function addAction(Application $app, Request $request)
    {
        error_log($request->getMethod());
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }
    
        if ($request->isMethod('POST'))
        {
    
            $g_name = $request->get('g_name');
            $t_name = $request->get('t_name');
            $risk = $request->get('risk');
            $classification = $request->get('classification');
            $contraindications = $request->get('contraindications');
            $oncology = array();
            foreach ($request->get('oncology') as $value) {
                if (!isset($value['approved']))
                    $approved = "";
                else 
                    $approved = $value['approved'];
                $arrayOfValues = array("cancer_type" => $value["type"], "approved" => $approved);
                $oncology[] = $arrayOfValues;
            }

            $precaution = array();
            foreach ($request->get('precaution') as $value) {
                $arrayOfValues = array("name" => $value["label"], "note" => $value["note"]);
                $precaution[] = $arrayOfValues;
            }

            $pregnancy = $request->get('pregnancy');
            $oraldose = $request->get('uo_dose');
            $breastfeeding = $request->get('breastfeeding');
            $fertility = $request->get('fertility');
            $metabolism = $request->get('metabolism');
            $excretion = $request->get('excretion');
            $available = $request->get('available');
            $administration = $request->get('administration');
            $monitoring = $request->get('monitoring');

            $frequency = $request->get('sideeffect_frequency');
            $sideeffect = $request->get('sideeffect');

            $interact = array();
            foreach ($request->get('interact') as $value) {
                if (empty($value['type'])) continue;

                if (!empty($value['enzymetype'])) {
                    $arrayOfValues = array("interaction" => str_replace('drug', $g_name, $value['type']), "compound" => $value['enzyme'], "enzyme_effect_type" => $value['enzymetype']);
                    $interact[] = $arrayOfValues;
                } else {
                    $arrayOfValues = array("interaction" => str_replace('drug', $g_name, $value['type']), "compound" => $value['name']);
                    $interact[] = $arrayOfValues;
                }
            }

            foreach ($request->get('interactQT') as $value) {
                $arrayOfValues = array("interaction" => $value['type'], "compound" => "QT-prolonging agents");
                $interact[] = $arrayOfValues;
            }

            foreach ($request->get('interact_other') as $value) {
                if (empty($value)) continue;
                $arrayOfValues = array("interaction" => "Other Interactions", "compound" => $value);
                $interact[] = $arrayOfValues;
            }

            $antineo = $request->get('anti_neoplastic');

            $adjustments = array();
            foreach ($request->get('adjustment') as $key => $value) {
                $arrayOfValues = array(
                    "problem" => $value['name'], 
                    "note" => $value['adjustment'], 
                    "chart_type" => $_FILES['adjustment']['type'][$key]['chart'], 
                    "chart" => $_FILES['adjustment']['tmp_name'][$key]['chart']
                );
                $ajustments[] = $arrayOfValues;
            }

            $last_revision = $request->get('last_revision');


            require 'api/dbConnect/connectStart.php';
            require 'api/put/putDrug.php';
            $drug = array(
                "g_name" => $g_name, 
                "t_name" => $t_name, 
                "risk" => $risk,              
                "classification" => $classification, 
                "pregnancy" => $pregnancy, 
                "breastfeeding" => $breastfeeding,
                "fertility" => $fertility, 
                "metabolism" => $metabolism, 
                "excretion" => $excretion, 
                "available" => $available, 
                "uo_dose" => $oraldose, 
                "last_revision" => $last_revision,
                "contraindications" => $contraindications,
                "monitoring" => $monitoring, 
                "administration" => $administration, 
                "anti_neoplastic" => $antineo, 
                "frequency" => $frequency,
                "sideEffects" => $sideeffect,
                "doseAdjusts" => $ajustments, 
                "drugInteracts" => $interact, 
                "oncUses" => $oncology,
                "precautions" => $precaution
            );
            if (putDrug($drug, $app['user']->getName(), $dbhandle)) {
                $app['session']->getFlashBag()->set('success', "Successfully added drug: ".$g_name);
                return $app->redirect($app['url_generator']->generate('console.drug.edit', array('ID' => $g_name)));
            } else {
                $app['session']->getFlashBag()->set('failure', "An error occured. Please try again.");
            }

            require 'api/get/listEnzymes.php';
            $enzymeList = getEnzymeList($dbhandle);

            return $app['twig']->render('drugs/add.html.twig', array(
                'enzymes' => $enzymeList
            ));
        } else {
            require 'api/get/listEnzymes.php';
            $enzymeList = getEnzymeList();

            return $app['twig']->render('drugs/add.html.twig', array(
                'enzymes' => $enzymeList
            ));
        }
        
    }

    /**
     * Shows and processes edit drug form.
     *
     * @route /console/drugs/{ID}
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
        require 'api/get/getDrug.php';
        require 'api/get/listEnzymes.php';

        if ($request->isMethod('POST')) {

            require 'api/post/postDrug.php';

            $name = $request->get('g_name_original');
            $orig_drug = getDrug($name, $dbhandle);


            $g_name = $request->get('g_name');
            $t_name = $request->get('t_name');
            $risk = $request->get('risk');
            $classification = $request->get('classification');
            $contraindications = $request->get('contraindications');
            $oncology = array();
            foreach ($request->get('oncology') as $value) {
                if (!isset($value['approved']))
                    $approved = "";
                else 
                    $approved = $value['approved'];
                $arrayOfValues = array("cancer_type" => $value["type"], "approved" => $approved);
                $oncology[] = $arrayOfValues;
            }

            $pregnancy = $request->get('pregnancy');
            $oraldose = $request->get('uo_dose');
            $breastfeeding = $request->get('breastfeeding');
            $fertility = $request->get('fertility');
            $metabolism = $request->get('metabolism');
            $excretion = $request->get('excretion');
            $available = $request->get('available');
            $administration = $request->get('administration');
            $monitoring = $request->get('monitoring');

            $frequency = $request->get('sideeffect_frequency');
            $sideeffect = $request->get('sideeffect');

            $interact = array();
            foreach ($request->get('interact') as $value) {
                if (empty($value['type'])) continue;

                if (!empty($value['enzymetype'])) {
                    $arrayOfValues = array("interaction" => str_replace('drug', $g_name, $value['type']), "compound" => $value['enzyme'], "enzyme_effect_type" => $value['enzymetype']);
                    $interact[] = $arrayOfValues;
                } else {
                    $arrayOfValues = array("interaction" => str_replace('drug', $g_name, $value['type']), "compound" => $value['name']);
                    $interact[] = $arrayOfValues;
                }
            }

            foreach ($request->get('interactQT') as $value) {
                $arrayOfValues = array("interaction" => $value['type'], "compound" => "QT-prolonging agents");
                $interact[] = $arrayOfValues;
            }

            foreach ($request->get('interact_other') as $value) {
                if (empty($value)) continue;
                $arrayOfValues = array("interaction" => "Other Interactions", "compound" => $value);
                $interact[] = $arrayOfValues;
            }

            $antineo = $request->get('anti-neoplastic');

            $last_revision = $request->get('last_revision');

            $drug = array(
                "g_name" => $g_name, 
                "t_name" => $t_name, 
                "risk" => $risk,              
                "classification" => $classification, 
                "pregnancy" => $pregnancy, 
                "breastfeeding" => $breastfeeding,
                "fertility" => $fertility, 
                "metabolism" => $metabolism, 
                "excretion" => $excretion, 
                "available" => $available, 
                "uo_dose" => $oraldose, 
                "last_revision" => $last_revision,
                "contraindications" => $contraindications,
                "monitoring" => $monitoring, 
                "administration" => $administration, 
                "anti_neoplastic" => $antineo, 
                "frequency" => $frequency,
                "sideEffects" => $sideeffect,
                "doseAdjusts" => $adjustments, 
                "drugInteracts" => $interact, 
                "oncUses" => $oncology
            );

            $qtProlongingList = array();
            foreach ($request->get('interactQT') AS $value) {
                if (empty($value['type'])) continue;
                $qtProlongingList[] = $value['type'];
            }

            // Drug Insertion
            $update["drugs"] = array(
                "g_name" => $drug["g_name"],
                "t_name" => $drug["t_name"],
                "risk" => $drug["risk"],
                "last_revision" => $drug["last_revision"],
                "classification" => implode('|', $drug["classification"]),
                "pregnancy" => $drug["pregnancy"],
                "breastfeeding" => $drug["breastfeeding"],
                "fertility" => $drug["fertility"],
                "metabolism" => $drug["metabolism"],
                "excretion" => $drug["excretion"],
                "available" => $drug["available"],
                "uo_dose" => $drug["uo_dose"],
                "contraindications" => $drug["contraindications"],
                "monitoring" => $monitoring,
                "administration" => implode('|', $administration),
                "anti_neoplastic" => $drug["anti_neoplastic"]?1:0,
                "other_interacts" => implode("\n", $request->get('interact_other')),
                "qt_prolonging" => implode('|', $qtProlongingList)
            );
            
            // Precaution Insertion
            $precautionList = array();
            foreach ($request->get('precaution') AS $value) {
                if (empty($value['label']) || empty($value['note'])) continue;
                $precautionList[$value['label']] = $value['note'];
            }
            $dbPrecautionList = array();
            foreach ($orig_drug['precautions'] AS $value) {
                $dbPrecautionList[$value['name']] = $value['note'];
            }

            $addPrecautionValues = array_diff_assoc($precautionList, $dbPrecautionList);
            $removePrecautionValues = array_diff_assoc($dbPrecautionList, $precautionList);

            foreach ($removePrecautionValues AS $key => $value) {
                $update["precautions"]["values"][] = array("drug" => $g_name, "name" => $key, "note" => $value);
                $update["precautions"]["options"][] = array("status" => "deleted", "pkey" => $key);   
            }
            foreach ($addPrecautionValues AS $key => $value) {
                $update["precautions"]["values"][] = array("drug" => $g_name, "name" => $key, "note" => $value);
                $update["precautions"]["options"][] = array("status" => "added", "pkey" => $key);
            }
        

            // Oncology Insertion
            $oncList = array();
            foreach ($request->get('oncology') AS $value) {
                if (empty($value['type'])) continue;
                $oncList[$value["type"]] = isset($value["approved"])?1:0;
            }
            $dbOncList = array();
            foreach ($orig_drug['oncUses'] AS $value) {
                $dbOncList[$value['cancer_type']] = $value['approved']?1:0;
            }

            $addOncValues = array_diff_assoc($oncList, $dbOncList);
            $removeOncValues = array_diff_assoc($dbOncList, $oncList);

            foreach ($removeOncValues AS $key => $value) {
                $update['onc_uses']['values'][] = array("drug" => $g_name, "cancer_type" => $key, "approved" => $value);
                $update['onc_uses']['options'][] = array("status" => "deleted", "pkey" => $key);
            }
            foreach ($addOncValues AS $key => $value) {
                $update['onc_uses']['values'][] = array("drug" => $g_name, "cancer_type" => $key, "approved" => $value);
                $update['onc_uses']['options'][] = array("status" => "added", "pkey" => $key);
            }
            

            // Side Effects Insertion
            $sideList = array();
            foreach ($request->get('sideeffect') AS $value) {
                if (empty($value['name'])) continue;
                $sideList[$value['name']] = isset($value['severe'])?1:0;
            }
            $dbSideList = array();
            foreach ($orig_drug['sideEffects'] AS $value) {
                $dbSideList[$value['name']] = $value['severe']?1:0;
            }

            $addSideValues = array_diff_assoc($sideList, $dbSideList);
            $removeSideValues = array_diff_assoc($dbSideList, $sideList);

            foreach ($removeSideValues AS $key => $value) {
                $update['side_effects']['values'][] = array("drug" => $g_name, "name" => $key, "severe" => $value);
                $update['side_effects']['options'][] = array("status" => "deleted", "pkey" => $key);
            }
            foreach ($addSideValues AS $key => $value) {
                $update['side_effects']['values'][] = array("drug" => $g_name, "name" => $key, "severe" => $value);
                $update['side_effects']['options'][] = array("status" => "added", "pkey" => $key);
            }


            // Drug Interactions Insertion
            $drugInterList = array();
            $enzyInterList = array();
            foreach ($request->get('interact') AS $value) {
                if (isset($value['enzyme']) && !empty($value['enzyme'])) {
                    if (empty($value['enzymetype'])) continue;
                    $enzyInterList[] = array("enzyme" => $value['enzyme'], "effect" => $value['type'], "type" => $value['enzymetype']);
                } else {
                    $drugInterList[$value['name']] = $value['type'];
                }
            }
            $dbDrugInterList = array();
            $dbEnzyInterList = array();
            foreach ($orig_drug['drugInteracts'] AS $value) {
                if ($value["compound"] == "QT-prolonging agents" || $value["interaction"] == "Other Interactions") continue;
                if (isset($value['enzyme_effect_type'])) {
                    $dbEnzyInterList[] = array("enzyme" => $value['compound'], "effect" => $value['interaction'], "type" => $value['enzyme_effect_type']);
                } else {
                    $dbDrugInterList[$value['compound']] = $value['interaction'];
                }
            }

            $addDrugInterValues = array_diff_assoc($drugInterList, $dbDrugInterList);
            $addEnzyInterValues = array_diff_assoc($enzyInterList, $dbEnzyInterList);
            $removeDrugInterValues = array_diff_assoc($dbDrugInterList, $drugInterList);
            $removeEnzyInterValues = array_diff_assoc($dbEnzyInterList, $enzyInterList);

            foreach ($removeDrugInterValues AS $key => $value) {
                if (empty($key) || empty($value)) continue;
                $update['drug_interacts']['values'][] = array("interaction" => $value, "compound" => $key);
                $update['drug_interacts']['options'][] = array("status" => "deleted", "pkey" => $value, "pkey2" => $key);
            }
            foreach ($removeEnzyInterValues AS $value) {
                if (empty($key) || empty($value['effect']) || empty($value['type'])) continue;
                $update['drug_interacts']['values'][] = array("enzyme_effect_type" => $g_name);
                $update['drug_interacts']['options'][] = array("status" => "deleted", "pkey" => $value['enzyme'], "pkey2" => $value['effect'], "pkey3" => $value['type']);   
            }
            foreach ($addDrugInterValues AS $key => $value) {
                $update['drug_interacts']['values'][] = array("drug" => $g_name, "compound" => $key, "interaction" => $value);
                $update['drug_interacts']['options'][] = array("status" => "added");
            }
            foreach ($addEnzyInterValues AS $value) {
                $update['drug_interacts']['values'][] = array("drug" => $g_name, "enzyme" => $value['enzyme'], "drug_effect_type" => $value['effect'], "enzyme_effect_type" => $value['type']);
                $update['drug_interacts']['options'][] = array("status" => "added");
            }


            // $expectedColumn = array("drugs", "drug_interacts","side_effects", "dose_adjusts", "precautions", "onc_uses");
            // $expectedColumnDrugInts = array("interaction", "compound", "drug");
            // $expectedColumnSEffects = array("drug", "name", "severe");
            // $expectedColumnDose = array("drug", "problem", "note", "chart");
            // $expectedColumnPre = array("drug", "name", "note");
            // $expectedColumnOncs = array("drug", "cancer_type", "approved");
            // $expectedColumnDrugCyp = array("enzyme", "drug", "drug_effect_type", "enzyme_effect_type");

            $adjustmentsList = array();
            $chartList = array();
            foreach ($request->get('adjustment') AS $key => $value) {
                if (empty($value['name'])) continue;
                $adjustmentsList[][$value['name']] = $value['adjustment'];
                $chartList[][$value['name']] = array(
                    "chart_type" => $_FILES['adjustment']['type'][$key]['chart'],
                    "chart" => $_FILES['adjustment']['tmp_name'][$key]['chart']
                );
            }
            $dbAdjustmentsList = array();
            foreach ($orig_drug['doseAdjusts'] AS $key => $value) {
                $dbAdjustmentsList[][$value['problem']] = $value['note'];
            }

            # TODO: Do this Next, requires some refactor in postDrug.php


            if (updateDrug($update, $app['user']->getName(), $name, $dbhandle)) {
                $app['session']->getFlashBag()->set('success', "Successfully edited drug: ".$g_name);
                if ($g_name != $orig_drug['g_name'])
                    return $app->redirect($app['url_generator']->generate('console.drug.edit', array('ID' => $g_name)));
            } else {
                $app['session']->getFlashBag()->set('failure', "An error occured. Please try again.");
            }

            foreach ($drug["drugInteracts"] as $value) {
                if ($value["compound"] == "QT-prolonging agents") {
                    $qtprolonging[] = $value["interaction"];
                } else if ($value["interaction"] == "Other Interactions") {
                    $othereffects[] = $value["compound"];
                } else {
                    $interactions[] = $value;
                }
            }

            $drug = getDrug($name, $dbhandle);
            $enzymeList = getEnzymeList($dbhandle);


            $interactions = array();
            $qtprolonging = array();
            $othereffects = array();
            foreach ($drug["drugInteracts"] as $value) {
                if ($value["compound"] == "QT-prolonging agents") {
                    $qtprolonging[] = $value["interaction"];
                } else if ($value["interaction"] == "Other Interactions") {
                    $othereffects[] = $value["compound"];
                } else {
                    $interactions[] = $value;
                }
            }

            $who_updated = $drug["who_updated"];

            // Query Database with ID and Return Drug Name and Information to Twig
            return $app['twig']->render('drugs/edit.html.twig', array(
                'drug' => $drug,
                'enzymes' => $enzymeList,
                'last_edited_by' => $who_updated,
                'interactions' => $interactions,
                'qtprolonging' => $qtprolonging,
                'othereffects' => $othereffects
            ));
        }

        
        $drug = getDrug($request->get('ID'), $dbhandle);
        $enzymeList = getEnzymeList($dbhandle);


        $interactions = array();
        $qtprolonging = array();
        $othereffects = array();
        foreach ($drug["drugInteracts"] as $value) {
            if ($value["compound"] == "QT-prolonging agents") {
                $qtprolonging[] = $value["interaction"];
            } else if ($value["interaction"] == "Other Interactions") {
                $othereffects[] = $value["compound"];
            } else {
                $interactions[] = $value;
            }
        }


        $who_updated = $drug["who_updated"];

        // Query Database with ID and Return Drug Name and Information to Twig
        return $app['twig']->render('drugs/edit.html.twig', array(
            'drug' => $drug,
            'enzymes' => $enzymeList,
            'last_edited_by' => $who_updated,
            'interactions' => $interactions,
            'qtprolonging' => $qtprolonging,
            'othereffects' => $othereffects
        ));
    }

    /**
     * Calls API to Show and Hide Drug
     *
     * @route /console/drugs/{ID}/showhide
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
            require 'api/delete/deleteDrug.php';
            $id = $app['user']->getName();
            $drugId = $request->get('ID');
            $showHide = $request->get('enabled');
            $response = showHideDrug($drugId, $id, $showHide);
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