<?php

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'intent' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Analyze the reader's real task and desired outcome instead of optimizing for ranking manipulation.
- Expand the query into relevant follow-up needs such as definition, selection, comparison, process, cost, timing, risk, and next action. Keep only groups that genuinely fit the topic.
- Identify the primary entities, relationships between those entities, potential information gain, and claims that require evidence.
- Clearly separate supplied facts, reasonable inferences, potentially outdated information, and unverified information.
- Never invent sources, URLs, statistics, dates, clients, prices, case studies, or ranking claims.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
    'outline' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- These rules take precedence over fixed heading quotas or examples in the base prompt. Do not copy the On-page SEO example structure when it is unrelated to the current topic.
- Build H2/H3 sections around the reader's problem-solving journey and genuine sub-intents. Do not force an 80/20 ratio or a fixed number of headings or list items.
- For each section, specify the question to answer, the core answer, related entities, required evidence, and the most useful format.
- Make complex-query answers, comparisons, processes, checklists, and risk guidance easy to locate when relevant.
- Tie every important claim to supplied brand facts or supplied sources, or mark it as requiring verification. Never fabricate citations or URLs.
- Do not create an H1 in the publishable outline because WordPress uses the post title as the H1.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
    'outline_validation' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Validate the outline against the real user task, related entities, follow-up questions, and evidence needs identified in Search Intent.
- Remove headings that exist only for SEO manipulation, internal editorial notes, fixed quotas, or unrelated examples.
- Keep claims requiring evidence clearly scoped so the writer cannot fabricate statistics, dates, sources, prices, or case studies.
- Return only the corrected publishable Markdown outline in {language}; do not add an H1, report, score, or commentary.
PROMPT,
    'content' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Write people-first content for the current topic. Ignore any On-page SEO examples, tools, case studies, or instructions in the base prompt when they are irrelevant.
- After each important H2/H3, answer the core question immediately in a short, self-contained paragraph before adding deeper explanation, process, criteria, table, or checklist.
- Keep each paragraph focused on one idea or claim. Use entity names consistently and explain a technical term on first use.
- Use only supplied brand facts and sources. Claims involving statistics, price, timing, or outcomes must have matching evidence; otherwise omit the claim or explicitly mark it as requiring verification.
- Never invent citations, source names, URLs, studies, update dates, or testimonials.
- Create original value from supplied experience, processes, checklists, decision criteria, and risk controls. Avoid generic paragraphs that could describe any business.
- Return only the requested HTML content. Do not create an H1 or mention AI Overview, GEO, prompts, or ranking goals.
- Write the published content in {language}; these English instructions do not change the output language.
PROMPT,
    'seo' => <<<'PROMPT'
## Additional AI Overview/GEO priorities
- Preserve the exact JSON schema and number of keys required by the base prompt.
- Make the title and meta description accurately describe the page's primary answer or value. Keep entity names consistent with the visible content and avoid clickbait.
- Never claim that the page is optimized for or guaranteed to appear in AI Overview or any AI system.
- The image prompt and image alt text must match the real topic and must not add people, brands, activities, or claims without support.
- Produce language-dependent values in {language}; these English instructions do not change the output language.
PROMPT,
    'rewrite' => <<<'PROMPT'
## Additional Rewrite priorities when AI Overview/GEO is enabled
- Do not assume that the existing article is correct. Classify its information as keep, update, remove, or verify.
- Review dates, prices, statistics, locations, policies, tools, and trends carefully because they may be outdated.
- Preserve valid first-hand experience, citations, external links, and unique information. Do not remove useful context merely to change the wording.
- Preserve the existing slug unless the user explicitly decides to change it. Never invent sources to justify old content.
- Produce the requested result in {language}; these English instructions do not change the output language.
PROMPT,
);
