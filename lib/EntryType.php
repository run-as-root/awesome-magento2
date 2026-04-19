<?php declare(strict_types=1);
namespace AwesomeList;

enum EntryType: string
{
    case GithubRepo      = 'github_repo';
    case Blog            = 'blog';
    case PackagistPkg    = 'packagist_pkg';
    case Event           = 'event';
    case YoutubePlaylist = 'youtube_playlist';
    case Course          = 'course';
    case VendorSite      = 'vendor_site';
    case Archive         = 'archive';
    case Canonical       = 'canonical';
}
