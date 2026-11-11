<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

/** The redirect addresses registered after the call. */
class RedirectUrlsResponse extends Response
{
    /** @return list<string> */
    public function getRedirectUrls(): array
    {
        $urls = $this->part()['redirectUrls'] ?? [];

        return array_values(array_filter(is_array($urls) ? $urls : [], 'is_string'));
    }
}
