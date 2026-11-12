<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Public route only: callers must retrieve models through publiclyVisible()/
 * visibleToPublic() query services before generating this metadata.
 * No unpublished source data or unreviewed images appear in metadata.
 */
final class PublicDetailSeo
{
    /** @return array{description:string,canonical:string,social_type:string,structured_data:array<string,mixed>} */
    public function forListing(Listing $listing): array
    {
        $property = $listing->property;
        $title = $property->title;
        $canonical = route('properties.show', $listing->slug);
        $fallback = 'Explore this '.strtolower((string) $property->property_type)
            .' available for '.$listing->transaction_type.' on Barama Real Estate.';
        $description = $this->describe($property->description, $fallback);

        return $this->build($canonical, $title, $description, [
            '@type' => 'Offer',
            'url' => $canonical,
            'price' => (string) $listing->price,
            'priceCurrency' => (string) $listing->currency,
            'itemOffered' => [
                '@type' => 'Residence',
                'name' => $title,
            ],
        ]);
    }

    /** @return array{description:string,canonical:string,social_type:string,structured_data:array<string,mixed>} */
    public function forProject(Project $project): array
    {
        $canonical = route('projects.show', $project->slug);
        $description = $this->describe(
            $project->description,
            'Explore the '.strtolower((string) $project->project_type)
                .' development '.$project->name.' on Barama Real Estate.'
        );

        return $this->build($canonical, $project->name, $description, [
            '@type' => 'Place',
            'name' => $project->name,
            'url' => $canonical,
        ]);
    }

    /** @return array{description:string,canonical:string,social_type:string,structured_data:array<string,mixed>} */
    public function forCompany(Company $company): array
    {
        $canonical = route('companies.show', $company->slug);
        $description = $this->describe(
            $company->description,
            'View publicly available projects and property listings from '
                .$company->name.' on Barama Real Estate.'
        );

        return $this->build($canonical, $company->name, $description, [
            '@type' => 'Organization',
            'name' => $company->name,
            'url' => $canonical,
        ]);
    }

    /** @param array<string,mixed> $entity
     * @return array{description:string,canonical:string,social_type:string,structured_data:array<string,mixed>}
     */
    private function build(string $canonical, string $name, string $description, array $entity): array
    {
        return [
            'description' => $description,
            'canonical' => $canonical,
            'social_type' => 'website',
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $name,
                'url' => $canonical,
                'description' => $description,
                'mainEntity' => $entity,
            ],
        ];
    }

    private function describe(?string $rich, string $fallback): string
    {
        $text = html_entity_decode(strip_tags((string) $rich), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = Str::squish($text);
        return Str::limit($plain !== '' ? $plain : $fallback, 155, '');
    }
}
