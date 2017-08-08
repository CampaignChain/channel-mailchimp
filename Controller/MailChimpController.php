<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Channel\MailChimpBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Location\MailChimpBundle\Entity\MailChimpUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class MailChimpController extends Controller
{
    const RESOURCE_OWNER = 'MailChimp';
    const LOCATION_BUNDLE = 'campaignchain/location-mailchimp';
    const LOCATION_MODULE = 'campaignchain-mailchimp-user';

    private $applicationInfo = array(
        'key_labels' => array('id', 'Client ID'),
        'secret_labels' => array('secret', 'Client Secret'),
        'config_url' => 'https://admin.mailchimp.com/account/oauth2/',
        'parameters' => array(),
        'wrapper' => array(
            'class'=>'Hybrid_Providers_MailChimp',
            'path' => 'vendor/campaignchain/channel-mailchimp/REST/MailChimpOAuth.php'
        ),
    );

    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if(!$application){
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        }
        else {
            return $this->render(
                'CampaignChainChannelMailChimpBundle:Create:index.html.twig',
                array(
                    'page_title' => 'Connect with MailChimp',
                    'app_id' => $application->getKey(),
                )
            );
        }
    }

    public function loginAction(Request $request){
        $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
        $status = $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo);
        $profile = $oauth->getProfile();

        if($status){
            try {
                $em = $this->getDoctrine()->getManager();
                $em->getConnection()->beginTransaction();

                $wizard = $this->get('campaignchain.core.channel.wizard');
                $wizard->setName($profile->displayName);

                // Get the location module.
                $locationService = $this->get('campaignchain.core.location');
                $locationModule = $locationService->getLocationModule(self::LOCATION_BUNDLE, self::LOCATION_MODULE);

                $location = new Location();
                $location->setIdentifier($profile->identifier);
                $location->setName($profile->displayName);
                $location->setImage($profile->photoURL);
                $location->setLocationModule($locationModule);
                $wizard->addLocation($location->getIdentifier(), $location);

                $channel = $wizard->persist();
                $wizard->end();

                $oauth->setLocation($channel->getLocations()[0]);

                $user = new MailChimpUser();
                $user->setLocation($channel->getLocations()[0]);
                $user->setIdentifier($profile->identifier);
                $user->setDisplayName($profile->displayName);
                $user->setFirstName($profile->firstName);
                $user->setLastName($profile->lastName);
                $user->setEmail($profile->email);
                $user->setProfileImageUrl($profile->photoURL);
                $user->setAddress($profile->address);
                $user->setCity($profile->city);
                $user->setCountry($profile->country);
                $user->setZip($profile->zip);
                $user->setPhone($profile->phone);
                $user->setWebsiteUrl($profile->webSiteURL);

                $em->persist($user);
                $em->flush();

                $em->getConnection()->commit();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'The MailChimp location <a href="#">'.$profile->displayName.'</a> was connected successfully.'
                );
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                throw $e;
            }
        } else {
            $this->get('session')->getFlashBag()->add(
                'warning',
                'A location has already been connected for this MailChimp account.'
            );
        }

        return $this->render(
            'CampaignChainChannelMailChimpBundle:Create:login.html.twig',
            array(
                'redirect' => $this->generateUrl('campaignchain_core_location')
            )
        );
    }
}