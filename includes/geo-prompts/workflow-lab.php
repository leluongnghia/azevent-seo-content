<?php

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'research' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Build a query fan-out from genuine sub-intents and likely follow-up questions. Do not create groups merely to increase keyword coverage.
- For every important topic, identify the entity, answerable claim, available evidence, supplied source or URL, freshness requirement, and missing information.
- Clearly separate verified facts, inferences, potentially outdated information, and unverified information.
- Recommend information gain grounded in real brand processes, checklists, decision criteria, experience, and supplied data.
- Do not treat SERP titles or snippets as sufficient evidence for a claim, and never fabricate citations.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
    'brief' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- For every H2/H3, define the sub-intent, core answer, related entities, claims requiring evidence, permitted sources, and freshness checks.
- When relevant, structure definitions, comparisons, processes, checklists, cost factors, timing, and risks as blocks that remain understandable on their own.
- Put a direct answer before deeper explanation, but do not force every heading into a question.
- Clearly mark claims that lack sufficient evidence so the Content step omits them instead of completing them from assumption.
- Never invent external URLs, research, statistics, or update dates.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
    'outline_validation' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Verify that every H2 serves a distinct sub-intent and provides standalone value. Merge sections that duplicate the same idea with different wording.
- Cover comparison, process, cost, timing, risk, and next-action queries when the approved Research shows they are relevant.
- Preserve a clear core answer, entity, evidence requirement, or verification warning for every important section.
- Do not add claims, citations, URLs, or facts beyond the supplied Research, Brief, and verified brand profile.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
    'content' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- After every important H2/H3, answer the core question in a short, self-contained paragraph before expanding.
- Keep each paragraph focused on one idea or claim. Use entity names consistently and explain a technical term on first use.
- Claims involving statistics, price, timing, outcomes, or comparisons must be supported by the Research or Brief. Otherwise omit the claim or state the verification condition.
- Use a citation or URL only when it is present in the supplied input and directly supports the sentence. Never invent a source.
- Prioritize practical information gain: processes, checklists, decision criteria, responsibilities, risks, and controls.
- Do not mention AI Overview, GEO, prompts, or ranking goals in the published content.
- Write the published content in {language}; these English instructions do not change the output language.
PROMPT,
    'seo' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Preserve the exact JSON schema required by the SEO step.
- Keep the title, meta description, focus keyword, and entities consistent with the visible article. Do not use clickbait or introduce new claims.
- Include FAQ schema only for complete questions and answers that are visibly present in the article.
- Never claim or imply guaranteed inclusion in AI Overview or any AI system.
- Produce language-dependent values in {language}; these English instructions do not change the output language.
PROMPT,
    'quality' => <<<'PROMPT'
## Additional AI Overview/GEO quality checks
- Check direct-answer coverage: each important question should receive a concise, clear, and correct answer before deeper explanation.
- Check claim-to-evidence consistency: important claims, statistics, prices, timing, outcomes, and comparisons must have matching support in the Research or Brief.
- Check citations: a URL must exist in the supplied input, support the exact claim, and never be fabricated. An unsupported citation is a critical issue.
- Check freshness: warn when time-sensitive information lacks a relevant date, scope, or verification condition.
- Check entity consistency, sub-intent coverage, information gain, and generic passages that could describe any business.
- Never request edits merely to repeat keywords or turn every heading into a question.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
);
