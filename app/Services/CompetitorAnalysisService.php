<?php

namespace App\Services;

class CompetitorAnalysisService
{
    public function __construct(
        private readonly BusinessProfileBuilder $businessProfileBuilder,
        private readonly BusinessClassifier $businessClassifier,
        private readonly SearchProfileBuilder $searchProfileBuilder,
        private readonly CompetitorSearchService $competitorSearchService,
        private readonly CompetitorRelevanceScorer $relevanceScorer
    ) {
    }

    public function analyze(
        array $websiteScan,
        ?array $googlePlace = null
    ): array {
        $businessProfile = $this->businessProfileBuilder->build(
            $websiteScan,
            $googlePlace
        );

        $classification = $this->businessClassifier->classify(
            $businessProfile
        );

        $searchProfile = $this->searchProfileBuilder->build(
            $businessProfile,
            $classification
        );

        $candidates = $this->competitorSearchService->findCandidates(
            $searchProfile,
            30
        );

        $topCompetitors = $this->relevanceScorer->rank(
            $candidates,
            $searchProfile,
            5
        );

        $strongMatchCount = count(
            array_filter(
                $topCompetitors,
                fn (array $competitor): bool =>
                    (bool) data_get(
                        $competitor,
                        '_relevance.strong_match',
                        false
                    )
            )
        );

        return [
            'business_profile' => $businessProfile,

            'classification' => $classification,

            'search_profile' => $searchProfile,

            'candidate_count' => count($candidates),

            'top_competitors' => $topCompetitors,

            'strong_match_count' => $strongMatchCount,

            'has_competitors' => $topCompetitors !== [],
        ];
    }
}