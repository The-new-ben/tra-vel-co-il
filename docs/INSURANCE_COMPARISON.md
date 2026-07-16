# Tra-Vel insurance comparison runtime

Tra-Vel V2 exposes a normalized decision contract at `POST /wp-json/tra-vel/v2/insurance/quote`. The contract compares medical limits, deductible, emergency dental, evacuation, repatriation, search and rescue, third-party liability, baggage, electronics, cancellation, trip shortening, delay, service model, requested extensions, exclusions, underwriting status and preliminary trip price. Non-sensitive defaults may also be read with `GET` for schema exploration.

The bundled `curated_demo_insurance` adapter contains fictional plans only. It is disconnected, non-purchasable and cannot expose checkout or policy links. A licensed commercial adapter can register with `tra_vel_v2_insurance_quote_adapters` and must implement `Tra_Vel_V2_Insurance_Quote_Adapter`. Supplier credentials, declarations and health details must remain server-side and should not be logged or cached beyond the minimum required by the approved provider workflow.

## Safety rules

- The insurer's policy wording, schedule, extensions, exclusions and underwriting decision always control.
- Medical conditions and pregnancy are represented only as assessment-required flags; the public comparison never asks for diagnoses or detailed health data.
- The browser submits quote inputs in a JSON request body. Requests containing assessment flags bypass persistent caches, return `Cache-Control: private, no-store`, and are rejected when sent through query-string `GET`.
- Activity recommendations identify questions to verify and never promise coverage.
- Demo prices are fictional estimates and never represent an insurer premium.
- No plan may become purchasable until provider terms, policy links, disclosures, consent, data handling and compliance review are approved.

These product fields are grounded in the [Israel Capital Market Authority travel-insurance marketing circular](https://www.gov.il/BlobFolder/dynamiccollectorresultitem/regulation-1035/he/regulation_h_2016-1-26%20%281%29.pdf) and current official insurer guidance on destination, duration, planned activity, medical disclosure and optional extensions.

## Public routes

- `POST /wp-json/tra-vel/v2/insurance/quote`
- `GET /wp-json/tra-vel/v2/insurance/health`
- `GET /wp-json/tra-vel/v2/insurance/schema`
- `DELETE /wp-json/tra-vel/v2/insurance/cache` — requires `manage_options`
