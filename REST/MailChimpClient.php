<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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