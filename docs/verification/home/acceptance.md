# HOME-001 Gate8 self-verification

All results apply only to the local candidate identified in artifact.json, identity.json and environment.json. Statuses below are implementer observations; independent Gate9 has not been performed by this task.

| Condition | Observed result and evidence | Limit / dependency |
|---|---|---|
| HOME-VU-A01 | Source mapping + 9 ordered modules + 59 link instances match the approved prototype. 218 typed fields; representative title/body/CTA/image/grade/SEO edits persist, invalid writes reject and exact content restores. source-runtime-parity.json, editor-results.json, content-before/restored.json. | Image picker opening and image-ID persistence are separate checks. |
| HOME-VU-A02 | Approved source CSS and brand assets mapped; computed geometry and five full-page screenshots captured. | Independent visual comparison remains Gate9 work. |
| HOME-VU-A03 | Open Hero; computed 56/44/36px, weight700, 2/2/3 lines at1440/768/390; approved image SHA matches actual uploaded file. | hero-*.png, layout-*.json, environment.json. |
| HOME-VU-A04 | Start Here remains immediately after Hero; 3 columns at >=768, 1 on mobile. Source parity and layout tests pass. | Five-width screenshots available. |
| HOME-VU-A05 | Four groups / 14 non-link labels; mobile defaults collapsed, keyboard expansion exposes all14. | products-expanded-390.png; browser test results. |
| HOME-VU-A06 | RFQ module visible at1440/1024/768 and display:none at390/320; shared approved RFQ links preserved. | RFQ target itself is not implemented (DEP-03). |
| HOME-VU-A07 | Single shared PHP Header/Footer/Menu; logo hashes retained. Cookie Settings uses approved inactive state, outline buttons, working close/focus return. No visitor cookies/storage/external requests observed. | Legal destinations are404 dependencies; privacy.json. |
| HOME-VU-A08 | All five widths have scrollWidth==viewportWidth; no page JS errors. Screenshots inspected for major overlap; no overflow clipping workaround. | Independent fine visual acceptance remains pending. |
| HOME-VU-A09 | Keyboard menus, focus containment/return, Escape, transparent backdrop, aria isolation, grade expansion and axe WCAG2/2.1 A/AA scans pass. | PARTIAL: physical AT, physical touch and native200% zoom NOT_VERIFIED; full touch-target audit not claimed. |
| HOME-VU-A10 | Server HTML has one H1, canonical, metadata and five-node/seven-relation graph; SEO edit/restoration passes; local noindex. | F01 was CLOSED_FOR_PACKAGE_V0.2 by D23 closeout; this task does not approve production SEO readiness. |
| HOME-VU-A11 | Correct site scope in code/data/media/header. Actual wrong-scope, missing-content and foreign-media states return503 without fallback; all restore exactly. | negative-runtime.json; environment.json. |
| HOME-VU-A12 | Actual file artifact fingerprint, DB/content fingerprint, restored snapshot, pinned runtime images, versions and source commit recorded. Original manifest/preflight scripts are invoked against final evidence HEAD. | Final output files are in ignored .runtime/handoff; runtime held until GATE9_PASS_OR_RETURN_NOTICE. |

## Dependencies and release limits

- DEP-01: actual WordPress fingerprint mapping is implemented; original validator and two-round preflight outputs accompany the handoff. Interface owner/independent Gate9 must confirm the mapping is acceptable.
- DEP-02: approved inputs checked, runtime source parity checked and content restored; this is initial-import evidence, not a runtime approval lock.
- DEP-03: homepage200; other24 approved URL targets return actual404. See dependencies.json. Owners of those pages must provide their behavior before relevant integration/release acceptance.
- DEP-04: independent Gate9 pending; this task cannot self-award that result.
- DEP-05: legal text, RFQ reception, full-site release configuration and explicit release authorization remain outside this batch. No production deployment, DNS change, indexing or analytics activation performed.

`editor-changed.png` is explicitly a temporary test state. All other candidate screenshots and final HTML use restored approved content. Test runs may have left extra site-owned test media attachments in the isolated library; the candidate uses attachment4 and the approved image hash. Restoring content does not restore or remove unrelated database rows or media.
