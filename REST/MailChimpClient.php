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

namespace CampaignChain\Channel\MailChimpBundle\REST;

use Symfony\Component\HttpFoundation\Session\Session;

class MailChimpClient
{
    const RESOURCE_OWNER = 'MailChimp';

    protected $container;

    protected $client;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function connectByActivity($activity){
        return $this->connectByLocation($activity->getLocation());
    }

    public function connectByLocation($location){
        // Get Access Token and Token Secret
        $oauthToken = $this->container->get('campaignchain.security.authentication.client.oauth.token');
        $token = $oauthToken->getToken($location);

        return $this->connect($token->getAccessToken(), $token->getEndpoint());
    }

    public function connectByNewsletterId($newsletterId)
    {
        $newsletter = $this->container->get('doctrine')->getRepository('CampaignChainOperationMailChimpBundle:MailChimpNewsletter')
            ->findOneByCampaignId($newsletterId);

        if (!$newsletter) {
            throw new \Exception(
                'No newsletter found with MailChimp Campaign ID '.$id
            );
        }

        return $this->connectByActivity($newsletter->getOperation()->getActivity());
    }

    public function connect($apiKey, $endpoint){
        $dc = explode('.', parse_url($endpoint, PHP_URL_HOST))[0];

        $this->client = new \Mailchimp($apiKey.'-'.$dc);

        return $this->client;
    }

    public function getNewsletterPreview($mailchimpCampaignId)
    {
        $newsletterContent = $this->client->campaigns->content(
            $mailchimpCampaignId,
            array(
                'view' => 'preview',
            ));

        return $newsletterContent['html'];
    }
}