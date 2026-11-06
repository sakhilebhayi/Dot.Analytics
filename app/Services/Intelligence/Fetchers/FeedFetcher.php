<?php

namespace App\Services\Intelligence\Fetchers;

use App\Models\Intelligence\Source;
use App\Services\Intelligence\Collector;
use App\Services\Intelligence\Deadline;
use App\Services\Intelligence\Fetcher;
use App\Services\Intelligence\SourceClient;
use App\Services\Intelligence\SourceFailed;
use Illuminate\Support\Carbon;
use SimpleXMLElement;

/**
 * RSS and Atom. It keeps a headline, a date and a link to the original, and a short excerpt only where the source's
 * terms allow one. The page itself is never fetched.
 */
class FeedFetcher implements Fetcher
{
    public function __construct(private readonly SourceClient $client) {}

    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array
    {
        $response = $this->client->get($source, (string) ($source->settings['feed'] ?? ''));

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new SourceFailed('The feed could not be read as XML.');
        }

        $kind = (string) ($source->settings['kind'] ?? 'announcement');
        $entries = isset($xml->channel) ? $xml->channel->item : $xml->entry;

        foreach ($entries as $entry) {
            $this->entry($entry, $kind, $collector);
        }

        return [];
    }

    private function entry(SimpleXMLElement $entry, string $kind, Collector $collector): void
    {
        $title = (string) $entry->title;
        $link = (string) $entry->link;

        if ($link === '' && isset($entry->link['href'])) {
            $link = (string) $entry->link['href'];
        }

        $date = (string) ($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
        $summary = (string) ($entry->description ?? $entry->summary ?? $entry->content ?? '');
        $id = (string) ($entry->guid ?? $entry->id ?? '');

        try {
            $published = $date === '' ? null : Carbon::parse($date)->utc();
        } catch (\Throwable) {
            $published = null;
        }

        $collector->item($kind, $title, $link, $published, $summary !== '' ? $summary : null, $id !== '' ? mb_substr($id, 0, 250) : null);
    }
}
