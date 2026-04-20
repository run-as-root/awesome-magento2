<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class CategoryGuesser
{
    private const RULES = [
        'extensions/payment.yml'              => ['payment','paypal','stripe','adyen','checkout'],
        'extensions/search.yml'               => ['search','solr','elasticsearch','algolia','fulltext'],
        'extensions/marketing.yml'            => ['seo','marketing','newsletter','email','campaign'],
        'extensions/cms.yml'                  => ['blog','cms','page','content'],
        'extensions/adminhtml.yml'            => ['admin','backend','grid','adminhtml'],
        'extensions/security.yml'             => ['security','gdpr','captcha','vuln'],
        'extensions/deployment.yml'           => ['deploy','ci/cd','pipeline','deployer'],
        'extensions/infrastructure.yml'       => ['docker','cache','redis','cron','infrastructure','queue'],
        'extensions/localization.yml'         => ['language','locale','translation','i18n','language-pack'],
        'extensions/pwa.yml'                  => ['pwa','hyva','tailwind','alpine','react','vue','headless'],
        'extensions/development-utilities.yml' => ['cli','magerun','debug','devtool','testing','phpstan','phpunit','mock'],
    ];

    public function guess(RepoSummary $repo): string
    {
        $haystack = strtolower(trim(($repo->description ?? '') . ' ' . $repo->fullName));
        foreach (self::RULES as $file => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return $file;
                }
            }
        }
        return 'extensions/_triage.yml';
    }
}
