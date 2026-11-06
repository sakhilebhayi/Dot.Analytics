# Dot.Analytics research: data rights, launch set, landscape

Date: 2026-11-06. Brief sections covered: 3, 4, 7, 14, 15.

**Tags.** [called] = I made the request with curl or a browser today and saw the response. [read] = I read the source's own page. [search] = search summaries only, not the primary page. Unverified = from memory or inference, not confirmed today.

**This is not legal advice.** The licence readings below are an engineer's reading of published terms. A South African IP lawyer should sign off the news rules and anything marked "ask" before launch. No repository was changed.

---

## 0. The short version

1. **Treat Dot.Analytics as commercial from day one, even while it is free.** It exists to send businesses into InfoDot and BluPin. Any source limited to "non-commercial" use, or barring use "in conjunction with the promotion of a commercial enterprise", is a risk now, not only when a paid tier arrives. This one framing removes gov.za, some WTO material, BBC and most publisher feeds, and puts WHO and FAO under a cloud. Unverified as law; it is the cautious reading.
2. **Open data is rich for indicators, thin for live markets, and very thin for news.**
   - Indicators: excellent, and better for South Africa than expected. Stats SA's licence is permissive and its files cover most sectors monthly.
   - Commodities: monthly prices are fine; daily oil and gas are fine (US EIA, public domain). Daily gold, platinum and all share-index data are not free to redistribute.
   - News: almost no commercial publisher permits this use. RSS being available gives no rights.
3. **The realistic news product at launch is a primary-source newsroom**, built from central banks, regulators, gazettes, filings and statistics releases, summarised by us from text we may use. Commercial headlines come later by licence.
4. **FRED is a trap.** Its terms bar storing or caching content, so it must not be an ingestion source. Use its upstream sources directly.
5. **World Bank is the legal backbone.** Its per-series metadata labels most IMF-, ILO-, ITU-, UNESCO- and IEA-origin series as CC BY-4.0, which gives a cleaner route than going to some of those bodies directly. The IEA-origin label has a caveat (section 3).
6. **SDMX is one connector, not many.** OECD, ECB, BIS, ILO, IMF, FAO, Eurostat and UNdata all answered the same protocol [called]. Build one SDMX connector, one RSS/Atom connector, one file connector and one REST-JSON connector.

---

## 1. What may be used: quick rights table

"Public" = may a free public site that promotes InfoDot show it. "Paid" = may a paid product use it. "Ask" = get written permission first.

| Source | Auth | Licence in one line | Public | Paid | SA/Africa |
|---|---|---|---|---|---|
| World Bank Indicators | none | CC BY 4.0 unless metadata says otherwise | Yes | Yes | All African countries, mostly annual |
| Stats SA | none | Free use with acknowledgement and a note on own processing | Yes | Yes, confirm by letter | SA monthly, the best source for SA |
| OECD Data API | none | Commercial use allowed; check third-party source per dataset | Yes | Yes, per dataset | ZAF monthly KEI |
| ILOSTAT | none | CC BY 4.0 per secondary source; primary page blocked | Yes, confirm | Yes, confirm | Strong, quarterly SA by sector |
| UNdata | none | Free to copy and distribute if UNdata cited | Yes | Likely, confirm | Broad |
| UNCTADstat | none | CC BY 3.0 IGO, cite as source | Yes | Likely, confirm | Broad, trade and FDI |
| Eurostat | none | Reuse allowed; non-EU country data excluded from commercial reuse | EU/EFTA data only | EU/EFTA data only | Avoid for Africa |
| ECB | none | Free use if accurate and cited; sellers must disclose it is free | Yes | Yes, with disclosure | ZAR only among African currencies [called] |
| BIS | none | Unrestricted if cited; no extra charge for including it in a commercial product | Yes | Bundle only, no surcharge | ZA covered; other Africa limited (Unverified) |
| Ember | free key for API, none for CSV | CC BY 4.0 | Yes | Yes | ZA yes; monthly file has 88 areas |
| US EIA | free key | US public domain, acknowledge | Yes | Yes | Global prices; country coverage needs a correct query |
| USGS | none | US public domain | Yes | Yes | Mining, SA central |
| GLEIF | none | CC0 | Yes | Yes | ZA 3,508 LEIs |
| SEC EDGAR | declared UA | Public, may be copied, credit SEC | Yes | Yes | Dual-listed SA miners |
| Wikidata | none | CC0 for structured data | Yes | Yes | Variable |
| APO / Africa Newsroom | none | Site states content is copyright free | Yes, labelled | Check issuers | Africa-specific |
| GOV.UK | none | Open Government Licence v3 | Yes | Yes | UK only |
| European Commission | none | CC BY 4.0 | Yes | Yes | EU only |
| Federal Reserve | none | Public domain unless marked, cite Board | Yes | Yes | US only |
| World Bank Pink Sheet | none | Licence not confirmed today | Ask | Ask | Includes SA coal |
| IMF | none | Commercial reuse needs permission; automated bulk barred without permission | Ask | Ask | Best forecasts, AFRREO |
| SARB | none | All rights reserved without written consent | Ask | Ask | Daily SA rates |
| gov.za | none | Non-commercial only; commercial needs GCIS written permission | Ask | Ask | Core SA government |
| WTO | free key | Commercial use needs written permission | Ask | Ask | Trade |
| UN Comtrade | key for scale | Licence applies; free apps treated differently from paid | Likely | Paid licence (older source) | Trade |
| FAOSTAT | none | CC BY 4.0 plus no promotion of a commercial enterprise | Risky | Risky | Agriculture |
| WHO GHO | none | Public-health purposes only; no promotion of a commercial enterprise | No | No | Use WB copies |
| IEA datasets | paid | Standalone datasets excluded from CC BY | No | Licence | Use Ember, EIA |
| FRED | key | No storing or caching; third-party rights | No | No | Use upstream |
| LBMA / IBA | licence | Licence needed to obtain, use or redistribute | No | Licence | Gold, platinum benchmarks |
| JSE, FTSE/JSE | licence | Licensed market data | No | Licence | SENS, indices |
| Yahoo Finance | none | Redistribution barred | No | No | Do not use |
| OpenCorporates | key | Free share-alike key needs proof of open contribution; otherwise paid | Unlikely | Paid | Has ZA |
| CIPC | application | No open bulk route found | No | Unknown | Register of record |

---

## 2. Part A: indicator sources, verified

### World Bank Indicators API (backbone)
- **Endpoint** `https://api.worldbank.org/v2/country/{iso}/indicator/{code}?format=json`
- **Example** `https://api.worldbank.org/v2/country/ZA/indicator/NY.GDP.MKTP.KD.ZG?format=json&per_page=3&mrv=3` returned 200 [called]
- **Shape** two-element array: `[{page,pages,per_page,total,sourceid,lastupdated}, [{indicator,country,countryiso3code,date,value,unit,obs_status,decimal}]]` [called]
- **Multi-country** `ZA;NG;KE` works; `mrnev=1` gives the latest non-empty value [called]
- **Auth** none. **Rate limit** none found. Unverified.
- **Updates** WDI `lastupdated` 2026-10-08 [called]. 71 sources listed [called]. Latest ZA GDP growth: 2025 = 1.11% [called].
- **History** to 1960 for many series. Unverified.
- **Licence** CC BY 4.0 unless the data or metadata says otherwise; commercial use allowed. Attribution format: The World Bank: Dataset name: Data source (if known). Sub-licences must carry the same requirement [read]. Disputes clause is a mandatory addition [read]. Third-party series may carry other terms, shown in metadata [read].
- **Per-series check** `https://api.worldbank.org/v2/sources/2/series/{code}/metadata?format=json` exposes `License_Type` and `License_URL` [called]. Store these at ingestion. Of 121 series tested, the restricted ones were the road and vehicle series (`IS.ROD.PAVE.ZS`, `IS.VEH.*`), which point at International Road Federation terms [called]. `EN.ATM.CO2E.KT` is archived and showed a NonCommercial label [called]. All others showed `CC BY-4.0` [called]. I checked metadata for WDI (source 2) only; other WB sources may differ.
- **Global Economic Monitor (source 15)** gives monthly series, including `DSTKMKTXN` stock market index in local currency, `RETSALESSA`, `IPTOTSAKD`, `UNEMPSA_`, `REER`, `IMPCOV` [called]. Example: `https://api.worldbank.org/v2/country/ZAF/indicator/DSTKMKTXN?source=15&format=json&mrv=3&frequency=M` returned ZA to 2026M08 [called]. Dataset page shows CC BY 4.0, last updated 2 Oct 2026 per catalogue, API shows 2026-09-08 [read][called]. Metadata credits Datastream as an input [called], so treat the index as a low-risk but not risk-free context line.
- **Lag warning** Tourism series stop at 2020 for ZA, renewables shares at 2021, mineral rents at 2021, hospital beds at 2010 [called]. Show the year. Never call these "current".

### Stats SA (the South African engine)
- **Index page** `https://www.statssa.gov.za/?page_id=1847` lists Excel and ASCII ZIPs [called, browser].
- **Files** `https://www.statssa.gov.za/timeseriesdata/Excel/{name}.zip` and `/Ascii/{name}.zip`. Example fetched with curl, 200, `application/zip`: `https://www.statssa.gov.za/timeseriesdata/Ascii/P5041.1%20Building%20Statistics(202608).zip` [called].
- **Shape** ASCII has header lines H01 to H25 (publication, title, series code, description, geography, price basis, unit, RELEASE, START, frequency) then one value per line [called]. Parsing is easy and stable-looking.
- **Trap** the index page is behind Incapsula: curl received a stub, a browser worked [called]. File names embed the release month and change, so a server job cannot simply discover new files. Options: ask Stats SA for a data feed or permission to automate; or maintain a manifest updated at each release from the published schedule. Do not evade the bot protection.
- **Also** RSS at `https://www.statssa.gov.za/?feed=rss2`, WordPress feed, last built 2026-11-05 [called, browser only]. Curl was blocked at the site pages, so test the feed from the production host.
- **Auth** none. **Frequency** monthly and quarterly per file; latest seen 202609 for CPI, PPI, electricity, liquidations [called].
- **History** files start 1993 or earlier by series; the ASCII example starts 1993-01 [called].
- **Licence** Products are copyright, but users may apply the information as they wish if they acknowledge Stats SA as the source of the basic data and state that the analysis results from their own processing [read]. This wording appears to allow commercial use, but it is not explicit, so get a confirming letter before any paid product.
- **Coverage** SA only; national and provincial GDP (P0441.2).

Monthly or quarterly files seen [called]: P0141 CPI, P0142.1 PPI, P0151.1 construction materials prices, P0160 residential property price index, P2041 mining production and sales, P3041.2 manufacturing production and sales, P3043 capacity utilisation, P4141 electricity generated, P5041.1 building statistics, P6141.2 wholesale, P6242.1 retail, P6343.2 motor trade, P6410 tourist accommodation, P6420 food and beverages, P7162 land transport, P0041 civil debt cases, P0043.1 liquidations, P0043.2 insolvencies, P0441.2 provincial GDP, P0142.7 trade unit values, P0045 quarterly (exact content Unverified).

### OECD Data API
- **Endpoint** `https://sdmx.oecd.org/public/rest/data/{agency},{dsd},/{key}?lastNObservations=n&format=...`
- **Example** `https://sdmx.oecd.org/public/rest/data/OECD.SDD.STES,DSD_KEI@DF_KEI,/ZAF.M......?lastNObservations=1&format=csvfilewithlabels` returned 200, 37 rows [called].
- **Useful ZAF monthly measures found** [called]: `PRVM` production volume (2026-08), `TOVM` retail trade volume (2026-08), `TOCAPA` passenger car registrations (2026-09), `NODW` dwelling permits (2026-08), `SHARE` share prices (2026-10), `IRLT`, `IR3TIB`, `EX`, `IM`, `CC`, `LI` leading indicator (2026-09).
- **Auth** none. **Rate limit** OECD reserves the right to throttle, no fixed figure on the terms page [read]. A limit of about 60 per hour is my memory. Unverified.
- **Licence** Data may be used for any purpose including commercial, with credit; format OECD (year), (dataset name), (data source) DOI or URL (accessed on date); sub-licences carry the requirement [read]. Exception: the user must check metadata for third-party ownership [read]. Written content from 1 July 2024 is mostly CC BY 4.0; content before needs checking [read]. Do not modify or redistribute the API itself [read].
- **Caution** Hyperlinks must not be used to promote a company [read], so keep OECD links in citations, not in sales material. `SHARE` for ZAF probably derives from a licensed index; check the dataset's source tab before showing it. Unverified.

### ILOSTAT
- **Endpoint** `https://rplumber.ilo.org/data/indicator/?id={id}&ref_area=ZAF&format=.csv&timefrom=2022` [called]. Also SDMX at `https://sdmx.ilo.org/rest/data/ILO,DF_UNE_2EAP_SEX_AGE_RT,/ZAF.A...?lastNObservations=1&format=csv` [called].
- **Shape** CSV: ref_area, source, indicator, sex, classif1, time, obs_value, obs_status, notes [called].
- **Useful** `EMP_TEMP_SEX_ECO_NB_Q` gives SA employment by sector quarterly to 2026Q2; annual to 2025 [called]. SA uses ISIC Rev.3 sections (C mining, D manufacturing, F construction, G trade, H hotels, I transport and communications, J finance, K real estate and business services) [called]. Note modelled projections appear with future years, such as 2027 in the SDMX sample [called]. Label them as modelled.
- **Limit** SDMX cap of 300,000 records [search]. Bulk facility exists [search].
- **Licence** CC BY 4.0 since 2023-05-03 per a third-party summary [search]. ILO's own terms page blocked me with a browser challenge, which I did not attempt to pass. Confirm by hand.

### IMF
- **SDMX** `https://api.imf.org/external/sdmx/2.1/data/IMF.RES,WEO/ZAF.NGDP_RPCH.A?startPeriod=2026` with `Accept: application/vnd.sdmx.data+csv` returned 6 rows [called]. 228 dataflows listed [called], including `IMF.AFR:AFRREO`, `IMF.RES:PCPS`, `IMF.STA:CPI`, `IMF.STA:IMTS`, `IMF.STA:BOP`, `IMF.STA:NA_MAIN` [called].
- **Datamapper** `https://www.imf.org/external/datamapper/api/v1/NGDP_RPCH/ZAF` works with curl's default user agent, 403 with a browser user agent [called]. Odd, and fragile.
- **Commodities** PCPS gold, `IMF.RES,PCPS/G001.PGOLD.USD.M`, returned 2026 monthly values [called].
- **Forecasts** ZA real GDP growth WEO: 2026 = 1.04, 2027 = 1.33, published 2026-10-13 [called].
- **Licence** Each record carries an all-rights-reserved notice pointing at IMF terms [called]. Terms allow copying, derivatives and redistribution with attribution, but commercial reuse needs prior permission, automated bulk download needs explicit permission, and training LLMs on content needs permission [read]. Sellers must tell buyers it is free from the IMF [read].
- **Verdict** Ask. Forecasts are the reason to ask; nothing else gives a single consistent forecast for every African economy. Until then, use WB copies of IMF-origin series, which WB labels CC BY-4.0 [called].

### ECB
- **Reference rates** `https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml`, ECB envelope with `Cube currency rate` elements, dated 2026-11-06 [called].
- **Data API** `https://data-api.ecb.europa.eu/service/data/EXR/D.ZAR.EUR.SP00.A?lastNObservations=2&format=csvdata`, ZAR 18.4268 on 2026-11-05 [called].
- **Auth** none. **Frequency** daily. **Africa** ZAR is the only African currency among the 29 in the daily file [called].
- **Licence** Free use if accurate and the ECB is cited; modifications such as growth rates must be stated; sold documents must tell buyers it is free from the ECB, before payment and at each access; site must not be framed; authored working papers need written permission to reproduce [read]. No fixed conditions for the API seen.

### BIS
- **Endpoint** `https://stats.bis.org/api/v1/data/{flow}/{key}?lastNObservations=n&format=csv` [called].
- **Called** `WS_CBPOL/D.ZA` SARB policy rate 7.25 on 2026-11-02; `WS_SPP/Q.ZA.R.628` real residential prices 2026-Q2; `WS_TC/Q.ZA.P.A.M.770.A` private credit [called]. 28 dataflows, including `WS_LONG_CPI`, `WS_EER`, `WS_DSR`, `WS_XRU` [called].
- **Licence** Use is unrestricted if BIS is cited, it does not mislead or imply endorsement, translations are flagged, and inclusion in a commercial product adds no extra charge [read]. Data Portal statistics are governed by these terms; general site material is non-commercial with limits [read].
- **Value** Lawful way to show the SARB policy rate without a SARB licence. BIS notes the data comes from central banks, which is what its terms cover.

### SARB
- **Endpoints** `https://custom.resbank.co.za/SarbWebApi/WebIndicators/HomePageRates`, `/CurrentMarketRates`, and `/WebIndicators/Shared/GetTimeseriesObservations/MMRD002A/2026-01-01/2026-11-06` [called]. No published docs found; `/Help` gave 404 [called]. Treat as undocumented.
- **Shape** JSON array: Name, SectionId, TimeseriesCode, Date, Value, UpDown, FormatNumber [called]. Daily to 2026-11-06.
- **Contents** Policy rate, prime, Sabor, Zaronia, JIBAR, T-bills, NCDs, bond yields R2030 and R209, ZAR crosses, NEER, CPI, PPI, and **gold price in USD and ZAR**, `GDPL201D` and `GDPL203D` [called].
- **Licence** Disclaimer states all IP is held by SARB and may not be copied, reproduced, adapted, published or distributed without prior written consent [read, browser]. No statistics exception found.
- **Verdict** Do not ingest until SARB grants written consent. This is the highest-value single permission for South Africa. The gold price there may also be third-party data, so ask specifically.

### Eurostat
- **Example** `https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/prc_hicp_minr?geo=EU27_2020&coicop18=TOTAL&lastTimePeriod=1&format=JSON` returned 200, JSON-stat, updated 2026-11-04 [called]. Older `prc_hicp_manr` is frozen at February 2026 [called], so series get replaced without notice. Check `updated`.
- **Licence** Commercial reuse allowed with acknowledgement, modifications stated, with a non-responsibility disclaimer. Not for commercial reuse: data for countries outside EU, EFTA and candidates (for example US, Japan, China), plus certain Swiss and Liechtenstein trade data [read]. Keep to EU and EFTA.

### UN family
- **UNdata** SDMX dataflows at `https://data.un.org/ws/rest/dataflow/` [called]. Terms: free, may be copied and redistributed with UNdata cited; no separate commercial clause; quality questions go to the source agency [read].
- **SDG API** `https://unstats.un.org/sdgs/UNSDGAPIV5/v1/sdg/Series/List?allreleases=false` [called]. Release 2026.Q3 seen [called]. Licence Unverified.
- **UNCTADstat** Bulk ZIP `https://unctadstat-api.unctad.org/bulkdownload/US.GDPTotal/US_GDPTotal` returned 200 [called]. Materials under CC BY 3.0 IGO; cite the UNCTAD Data Hub; free, no registration [read]. Commercial clause not explicit.
- **UN Comtrade** Preview `https://comtradeapi.un.org/public/v1/preview/C/A/HS?reporterCode=710&period=2023&partnerCode=0&flowCode=X&cmdCode=TOTAL`, no key, 1 record [called]. Free key about 500 calls a day [search]. A 2014 policy waives the fee for free-of-charge applications but limits bulk redistribution [search]. Policy page URL I tried was 404, so confirm the current text.

### WTO
- **API** `https://api.wto.org/timeseries/v1/indicators?i=all&lang=1` returned 401 without key [called]. Free key via portal [read]. Bulk CSV exists [read].
- **Licence** Official publications may be reproduced for non-commercial purposes with attribution and notice to the WTO; commercial use needs written permission [read]. No separate statistics licence found. A WTO tariff-data terms document bars mass redistribution without approval [search]. Ask.

### FAOSTAT
- **Endpoints** `https://bulks-faostat.fao.org/production/datasets_E.json` bulk index [called]; `https://nsi-release-ro-statsuite.fao.org/rest/dataflow/all/all/latest?detail=allstubs` SDMX [called]; old service `faostatservices.fao.org` needs authorisation [called].
- **Licence** CC BY 4.0, plus terms: no use for or with promotion of a commercial enterprise or its products or services, no implied endorsement, third-party data may be excluded. Citation format fixed [read].
- **Issue** The promotion clause conflicts with a site that promotes InfoDot. Route: use WB-held FAO-origin series (labelled CC BY-4.0 [called]), or keep FAO pages free of ecosystem promotion, or ask FAO.

### WHO GHO
- **API** `https://ghoapi.azureedge.net/api/WHOSIS_000001?$filter=SpatialDim eq 'ZAF'&$top=2`, OData JSON, no key [called].
- **Licence** Licensed use is for public health purposes; no selling; no promotion of a commercial enterprise; only minimal alteration [read]. Do not use. Use WB copies labelled CC BY-4.0 [called].

### EIA
- **Endpoint** `https://api.eia.gov/v2/petroleum/pri/spt/data/?frequency=daily&data[0]=value&facets[series][]=RBRTE&length=2&sort[0][column]=period&sort[0][direction]=desc&api_key=KEY` (use `curl -g`). Returned Brent $107.03 on 2026-11-03 with DEMO_KEY [called].
- **Limits** DEMO_KEY shows `x-ratelimit-limit: 10`; responses capped at 5,000 rows [called]. Register a free key.
- **Licence** US government publications are public domain; use freely; acknowledge EIA; third-party material and logo excluded [read].
- **International route** exists, but my ZAF query returned 0 rows; facet IDs need work [called].

### IEA
- API exists; requires a series [called]. Reports and standalone charts are CC BY 4.0; datasets, data explorers, WEO annexes and the Oil Market Report are excluded [read]. Non-CC material: insubstantial amounts only, non-commercial, not regular [read]. Do not ingest.

### Ember
- **CSV** `https://files.ember-energy.org/public-downloads/generation/outputs/release_generation_monthly_global.csv`, 200, 4.5 MB, last modified 2026-11-03 [called]. Old URL replaced with a notice row [called], so read notices.
- **Columns** Area, ISO 3 code, Date, Electricity source, generation, share, YoY, emissions, intensity, region flags [called].
- **API** `https://api.ember-energy.org/v1/electricity-generation/yearly?entity_code=ZAF&...` needs a free key [called].
- **Licence** All content CC BY 4.0 [read]. Monthly dataset covers 88 areas; yearly over 200 [read]. Twice-monthly updates [read].

### USGS Mineral Commodity Summaries
- MCS 2026 chapters at `https://pubs.usgs.gov/periodicals/mcs2026/` and data releases on ScienceBase [search]. ScienceBase JSON was blocked by a challenge from curl [called]. Download CSVs by hand each year.
- **Licence** USGS-authored data is US public domain; some third-party images excluded [read].
- **Frequency** annual, published early each year (Unverified).

### Others tested
- **Frankfurter** `https://api.frankfurter.dev/v2/rates?base=USD&quotes=NGN,KES,ZAR` returned NGN 1346.33, KES 129.84, ZAR 16.447 [called]. 108 providers, including SARB, CBN, CBK, CBE, BOB, BOZ, BOTA, BNA, BAM, BCT, BOA, NBE, BNRRW and BM [called]. Code MIT [read]. No key. Open source, self-hostable [read]. **Rights:** the code is MIT, but rates come from central banks whose own terms vary; most `terms_url` are null [called]. SARB is in there, so the SARB consent problem carries through. Exclude `SARB` and use `providers=` to control sources. Confirm each African central bank before publishing rates daily.
- **DBnomics** `https://api.db.nomics.world/v22/providers` reports 47,444 datasets and 1.7 billion series [called]. Per-provider `terms_of_use`. Useful for discovery; rights remain with the upstream.
- **OWID** `https://ourworldindata.org/grapher/gdp-per-capita-worldbank.csv` and `.metadata.json` [called]. Own work CC BY; most data is third-party and follows that provider's terms [read]. Grapher code has a custom licence; reuse needs permission [read]. Filter parameter `country=ZAF` did not filter in my call [called]. Excellent model, not a data source.
- **WIPO** IP statistics page live [called]. Terms not read.
- **ITU DataHub** API 403 [called]. Use WB copies.
- **UNIDO, AfDB** Blocked by Cloudflare from curl [called]; terms not read. A 2026 listing shows blank licence fields for Open Data for Africa portals [search]. Treat AfDB as unknown. Africa Development Indicators in WB last updated 2013 [called].
- **UNESCO UIS** `https://api.uis.unesco.org/api/public/data/indicators?indicator=CR.1&geoUnit=ZAF&start=2020&end=2024` returned JSON [called]. Terms page gave no text to my tool [called]. Use WB copies.
- **Eskom Data Portal** Hourly demand, generation build-up, renewables, outage and emissions datasets; downloads via emailed request up to 5 years; no API stated [read]. Terms not retrieved. Ask. High value for SA.
- **SARS trade portal** Reachable [called]; terms not read.
- **IMF PortWatch** ArcGIS endpoint rejected me with a 429: quota exceeded across the whole org, including after waiting [called]. IMF terms apply. Do not depend on it yet.
- **data.gov.za** did not resolve [called].

---

## 3. Part A: prices and markets, strictly

| Need | Legal free route | Not free |
|---|---|---|
| Oil, gas spot | EIA daily, public domain [called][read] | Platts, Argus (Unverified) |
| Gold, silver | Pink Sheet monthly (confirm licence); IMF PCPS monthly (ask) | LBMA Gold and Silver Price: licence from IBA needed to obtain, use or redistribute, real-time or historical [read] |
| Platinum, palladium | Pink Sheet monthly (confirm) | LBMA Platinum and Palladium: same, now run by IBA [search] |
| Copper, iron ore, nickel, aluminium, coal (Richards Bay), maize, wheat | Pink Sheet monthly (confirm) | LME and exchange data, licensed (Unverified) |
| Daily FX | ECB, plus central banks after terms check | Interbank vendor rates (Unverified) |
| JSE All Share, Top 40 | WB `DSTKMKTXN` monthly index, OECD `SHARE` monthly (check source) | FTSE/JSE indices and constituent data under licence with a Multi-Use Policy [search] |
| Global indices | None clean at daily frequency | S&P, Dow, Nasdaq, FTSE (Unverified) |
| Listed company prices | None | Exchanges and vendors |

**World Bank Pink Sheet.** `https://thedocs.worldbank.org/en/doc/74e8be41ceb20fa0da750cda2f6b9e4e-0050012026/related/CMO-Historical-Data-Monthly.xlsx` returned 200; sheet "Monthly Prices", 1960 to present, updated 2026-11-03; October 2026: gold 4,105, platinum 1,621, silver 57, copper 14,508, iron ore 90.8, Brent 125, SA coal 100 [called]. The URL hash changes between releases, so discover it from `https://www.worldbank.org/en/research/commodity-markets` [called]. Monthly only.

**Cross-check passed** Pink Sheet Brent for October 2026 is 125; the EIA monthly average for October is 123.93 [called]. EIA daily fell from 120.15 on 30 October to 107.03 on 3 November [called]. A monthly number can therefore look far from the latest daily one, so any page showing both must state the period of each.

**Pink Sheet licence is not confirmed.** The Pink Sheet page has no licence text [called]. A third party labels it CC BY 4.0 [search], and the WB dataset terms apply only if it is a catalogued dataset [read]. It draws on commercial price reporters [called, file notes]. Ask the World Bank Prospects Group. Plain site terms are non-commercial for non-dataset material [read], so this matters.

**FRED.** FRED removed LBMA gold and other IBA series on 2022-01-31 [search]. FRED's terms bar storing, caching or archiving content, incorporating it in a database, and commercial use without consent; third-party series need the owner's permission; API users must show a non-endorsement notice and may not replicate FRED's core experience [read]. Direct `fredgraph.csv` failed from curl at the HTTP layer [called]. Do not use.

**Yahoo Finance.** The chart endpoint answered for `^J203.JO` [called], which proves nothing about rights. Yahoo's help text bars redistribution [search]; yfinance states personal use only and no affiliation [search]. Do not use.

**JSE.** All JSE pages 403 from curl [called]. Licence plus reported usage fees, a live SENS licence, an index multi-use policy; JSE does not offer a direct delayed feed [search]. SENS retirement reported under JSE's 2031 strategy [search]. Ask for a price at the paid stage.

**TradingView widgets.** A legal-looking shortcut for showing a JSE ticker tape. Terms require visible attribution; a policy page says commercial use needs a separate agreement [search]. JSE delayed data in widgets unconfirmed [search]. Unsettled, and it hands the visitor to TradingView. Not recommended until confirmed in writing.

**Alpha Vantage.** Terms exist as a PDF I could not parse. Unverified.

---

## 4. Part A: news and developments

**The rule.** A feed being public gives no right to republish it. Below, "Display" means headline, date, source and link. "Summary" means a short summary.

### Usable now

| Feed | URL tested | Items | Display | Summary | Notes |
|---|---|---|---|---|---|
| Federal Reserve | `https://www.federalreserve.gov/feeds/press_all.xml` | 20 | Yes | Yes | Public domain unless marked, cite Board; do not frame [read] |
| ECB press | `https://www.ecb.europa.eu/rss/press.html` | 15, title only | Yes | Yes, ours | Free use, accurate, cited [read] |
| ECB working papers | `https://www.ecb.europa.eu/rss/wppub.html` | 15, abstract | Title, link | Our own only | Authored papers need permission to reproduce [read] |
| European Commission | `https://ec.europa.eu/commission/presscorner/api/rss?language=en` | 10 | Yes | Yes | CC BY 4.0, third-party excluded [read] |
| GOV.UK | `https://www.gov.uk/government/organisations/{org}.atom` | 20 | Yes | Yes | OGL v3, commercial allowed, attribution statement [read] |
| SEC press | `https://www.sec.gov/news/pressreleases.rss` | 25 | Yes | Yes | Public; credit SEC [read]; 10 requests a second; declare UA [read] |
| EDGAR current | `https://www.sec.gov/cgi-bin/browse-edgar?action=getcurrent&type=8-K&count=5&output=atom` | 10 | Yes | Yes | Filings are issuer documents; do not copy third-party exhibits (Unverified) |
| Federal Register | `https://www.federalregister.gov/api/v1/documents.json` | full API | Yes | Yes | No key [called]; US federal works generally public domain, Unverified |
| Stats SA | `https://www.statssa.gov.za/?feed=rss2` | WordPress | Yes | Yes | With acknowledgement [read] |
| APO Africa Newsroom | `https://www.africa-newsroom.com/africarc/rss` | 10, full text | Yes | Yes | Terms and site state content is copyright free, free to republish [read]. Press releases. Label as such. Issuer clearances are not APO's responsibility [read] |
| World Bank documents | `https://search.worldbank.org/api/v3/wds?format=json&rows=2&strdate=2026-10-01&fl=display_title,docdt,url` | 3,925 in range | Title, link | Check item | Mixed licences; rows include future-dated documents [called] |

### Blocked or restricted

| Source | Technical result | Rights result | Decision |
|---|---|---|---|
| gov.za | `https://www.gov.za/rss.xml` 200, 10 items [called] | Non-commercial only; commercial needs prior written permission from GCIS [read] | Ask. Top SA permission |
| Competition Commission | `https://www.compcom.co.za/feed/` works but newest item seen dated 2018 [called] | Terms not read | Scrape not advised; ask |
| National Treasury | `rss.aspx` returned HTML, no items [called] | Terms not read | Manual for now |
| DMRE | Timed out [called]. Department was split; name may have changed, Unverified | Unknown | Manual |
| SARB | Tested RSS path 404 [called] | All rights reserved [read] | Ask |
| JSE SENS | 403 [called] | Licensed [search] | Paid stage |
| IMF RSS | 403 from curl [called] | Commercial use needs permission [read] | Link manually |
| World Bank news | Tested path 404 [called] | Site terms non-commercial [read] | Use docs API |
| WTO news | `https://www.wto.org/library/rss/latest_news_e.xml` 10 items [called] | Copyright; commercial needs permission [read] | Ask |
| AfDB, OECD newsroom, BLS | 403 [called] | OECD press releases may be reproduced with citation [read] | Manual link |
| UK BBC | 54 items [called] | Business use of RSS needs permission and possibly a fee; metadata needs a licence [read] | No, licence |
| Guardian | RSS 200, "all rights reserved" [called] | Developer key non-commercial, 500 calls/day; commercial key priced; delete content within 24 hours; end users personal use only; no using headlines to link anywhere except the full article in original format [read] | Commercial key only |
| Reuters | No public feed; tested URL 404 [called]; feeds retired 2020 [search] | Terms page 401 to me, unread | Licence |
| AP | 403 [called] | Terms not read | Licence |
| Daily Maverick | `https://www.dailymaverick.co.za/dmrss/` 50 items [called] | Terms bar republishing, reproducing, redistributing [read] | Ask |
| BusinessTech | Feed 200, 20 items [called] | Terms page not found | Treat as all rights reserved |
| Moneyweb | Feed 200, 10 items [called] | Terms read in part; litigated copyright in news in 2016 [search] | Ask |
| Business Day, Miningmx, Engineering News | Tested URLs 404 or 403 [called] | Not read | Ask |
| TechCabal | Feed 200, 10 items [called] | Terms blocked 403 | Ask |
| MyBroadband, Bizcommunity | Feeds 200 [called] | Not read | Ask |
| allAfrica | Feed 200, 31 items [called] | No copying without written permission; items belong to content partners, who must each consent [read] | Licence |
| PR Newswire | Feed 200, 20 items [called] | Personal non-commercial; no republishing or data mining without written consent; no AI training [read] | No |
| Business Wire | Feed 200; feed asserts copyright [called] | Terms blocked | No |
| GlobeNewswire | 403 [called] | Not read | No |
| GDELT | See below | See below | Discovery |

### GDELT
- **Endpoint** `https://api.gdeltproject.org/api/v2/doc/doc?query=mining%20sourcecountry:southafrica&mode=artlist&maxrecords=3&format=json&timespan=2d` [called].
- **Fields** url, title, seendate, socialimage, domain, language, sourcecountry [called]. No body and no summary.
- **Limits** One request every 5 seconds, enforced with a 429; my call took 25 seconds [called]. Query it from a queue, never a page load.
- **Licence** Datasets unrestricted for commercial use and may be redistributed with a citation and link to gdeltproject.org [read].
- **Caution** GDELT can license its own records. It cannot license the publisher's headline or `socialimage`. Never show the images.
- **Quality** A "mining" query returned an IT-security article and an environmental statement [called]. Do not auto-publish. Use as a signal for trending topics and for editors, or show only with a filter.

### Law: a short note for counsel
- SA: *Moneyweb v Media24*, [2016] 3 All SA 193 (GJ). One of seven articles infringed; four failed on originality; speed counted against fair dealing; commentary suggests headline and intro were not a substantial part in that case [search]. Secondary reports only; the judgment was not read.
- UK: *NLA v Meltwater* [2011] EWCA Civ 890. Headlines can be literary works; not all are [search].
- EU press publishers' right and *AP v Meltwater*: not confirmed by my search. Unverified.
- SA Copyright Act treatment of official texts and news of the day: section reference not confirmed. Unverified. Ask counsel, since it may help with gazette notices.
- Reuters Institute 2026, via secondary coverage: 10% weekly use of chatbots for news, 4% regularly click through, trust in news 37% [search]. These argue for showing sources visibly, and publishers will care about summaries that replace visits.

### AI summaries
Summarise only text we hold rights to: primary documents, CC or public domain sources. Never summarise a publisher's article from its feed or by fetching it. Keep the source text linked, label the summary as AI-generated (brief section 7), and record model, source ID and hash. ECB, IMF, PR Newswire, Guardian and BBC all have terms that touch this [read].

---

## 5. Part A: companies and research

| Source | Example | Auth, limits | Licence | Use |
|---|---|---|---|---|
| GLEIF | `https://api.gleif.org/api/v1/lei-records?filter[entity.legalAddress.country]=ZA&page[size]=1`, JSON:API, golden copy 2026-11-06 [called] | None; limit Unverified | CC0, free [read]; a protection clause for GLEIS exists [read] | Spine. ZA 3,508; NG 1,016; EG 344; KE 311; GH 141 [called]. Entities with LEIs, often funds; not a business census |
| SEC EDGAR | `https://data.sec.gov/submissions/CIK0000314590.json` Sasol [called]; also Harmony, Gold Fields [called] | 10 a second, declare UA [read]. Use a role mailbox, never a personal address | Public, credit SEC [read] | **SA angle:** dual-listed filers give free, lawful announcements while SENS is licensed |
| Companies House | `https://api.company-information.service.gov.uk/search/companies?q=anglo` 401 without key [called] | Free key, 600 per 5 minutes [read] | Crown copyright, OGL likely, confirm | UK entities |
| OpenCorporates | 401 without token [called] | Free share-alike key needs evidence of contributing back; commercial is paid [read] | Share-alike or paid [read] | Not for launch |
| CIPC | No open API or bulk licence found; vendors resell [search] | Application [search] | Not found | Do not buy unverified resold data. Beneficial ownership is not public [search] |
| Wikidata | SPARQL endpoint works; search API works [called]. JSE is Q627514 [called] | Limit Unverified | Structured data CC0 [read] | Names, tickers, HQ; data quality varies. My test query returned 0 rows, so the query needs work |
| InfoDot directory | Internal | Internal | Ours, respecting visibility | The asset nobody else has |
| OpenAlex | `https://api.openalex.org/works?search=south%20africa%20mining&per_page=1` [called] | No key: shared $0.10 daily budget by IP; free key $1 [called] | Believed CC0. Unverified | Research discovery |
| Crossref | `https://api.crossref.org/works?query=african%20manufacturing&rows=1` [called] | Headers show 1 a second, concurrency 1 [called]; docs say 5 for public [read]. Polite pool (10 a second [read]) reportedly needs a contact email, Unverified; use a role mailbox | Metadata licence page not found. Unverified | DOI lookups |
| World Bank OKR | `https://openknowledge.worldbank.org/server/api/discover/search/objects?query=south%20africa&size=1` [called] | Unverified | Mixed: CC BY, CC BY 3.0 IGO, CC BY-NC, CC BY-NC-ND per item [read] | Read licence per item; NC is out |
| OECD publications | Pages blocked from curl | n/a | Most from 1 July 2024 CC BY 4.0 [read] | Link and summarise |
| IMF working papers | Blocked from curl; DOIs surface in Crossref [called] | n/a | Commercial needs permission [read] | Title and link |
| ECB working papers | RSS [called] | n/a | No reproduction without permission [read] | Title and link |
| NBER | `https://www.nber.org/rss/new.xml` 200 [called] | n/a | Not read | Title and link |
| arXiv econ | `https://export.arxiv.org/api/query?search_query=cat:econ.GN&max_results=1&sortBy=submittedDate` [called] | Unverified | Per paper | Title, abstract, link |
| CORE | `https://api.core.ac.uk/v3/search/works?q=south%20africa&limit=1` [called] | Key for scale, Unverified | Not read | Backup |

---

## 6. Ranked launch set

Ranked by how much each makes the site useful for the least legal risk.

| # | Source | Gives | Connector | Refresh | Clearance before launch |
|---|---|---|---|---|---|
| 1 | World Bank Indicators + GEM | Country and sector profiles for every country, monthly index and retail for many | REST | Weekly check, on `lastupdated` | Store per-series licence |
| 2 | Stats SA ZIPs + RSS | SA monthly by sector | File, ASCII parser | Per release calendar | Letter recommended; agree automation |
| 3 | OECD KEI and CLI | Monthly ZAF plus peers: retail, cars, permits, production | SDMX | Monthly | Check third-party tab per measure |
| 4 | ILOSTAT | Jobs by sector, quarterly | CSV | Quarterly | Confirm licence page |
| 5 | EIA | Daily Brent, WTI, gas | REST | Daily | Free key |
| 6 | World Bank Pink Sheet | Monthly gold, platinum, copper, iron ore, SA coal, maize | XLSX | Monthly, early | **Confirm licence** |
| 7 | ECB reference rates | Daily ZAR and majors | XML | Daily | None |
| 8 | BIS | Policy rates including SARB, property, credit | SDMX | Weekly | Cite; no surcharge later |
| 9 | Primary newsroom | Fed, ECB, EC, GOV.UK, SEC, Federal Register, Stats SA, APO | RSS/Atom | Hourly | Label press releases |
| 10 | Ember | Electricity mix, solar and wind, emissions | CSV | Twice monthly | None |
| 11 | USGS MCS | Mineral production by country | CSV yearly | Annual | Manual download |
| 12 | UNCTADstat | Trade, FDI, shipping | Bulk ZIP | Monthly check | Confirm commercial position |
| 13 | SEC EDGAR + GLEIF + Wikidata | Company pages, SA dual-listed filings | REST | Daily | Role-based UA |
| 14 | OpenAlex + Crossref + OKR | Research centre | REST | Daily | Free key via role account; item-level licence for OKR |
| 15 | GDELT | Topic trends, editor signals only | REST queue | 15 minutes | Not shown as a news list until counsel approves |

**First permission letters, in order:** SARB; GCIS (gov.za); World Bank Prospects Group (Pink Sheet); IMF; Stats SA (confirmation and automation); WTO; Eskom. Write them as one standard template with the use case stated honestly, including that the site promotes InfoDot.

**Paid stage quotes:** IBA for LBMA prices; JSE for delayed indices and SENS; one licensed news source such as a Guardian commercial key, or an African publisher partnership.

---

## 7. Industry coverage, with real indicators

Every WDI code below returned a ZA value today; the year in brackets is the latest [called]. Stats SA numbers are files seen today [called]. OECD codes are `DSD_KEI` measures for ZAF [called].

| Industry | SA, monthly or quarterly | Global / annual | Commodities, other | Honest rating |
|---|---|---|---|---|
| Mining and minerals | P2041 production and sales; ILO ISIC3 C | `NY.GDP.MINR.RT.ZS` (2021), `TX.VAL.MMTL.ZS.UN` (2025), `TM.VAL.MMTL.ZS.UN` (2025) | Pink Sheet gold, platinum, iron ore, copper, nickel, SA coal; USGS MCS; SEC filers | **Strong** |
| Agriculture | P1101 annual; P0141 food CPI; ILO ISIC3 A | `NV.AGR.TOTL.ZS` (2025), `SL.AGR.EMPL.ZS` (2025), `AG.PRD.CREL.MT` (2024), `AG.YLD.CREL.KG` (2024), `AG.PRD.FOOD.XD` (2024), `TX.VAL.FOOD.ZS.UN` (2025) | Pink Sheet maize, wheat; FAOSTAT with promotion caveat | **Strong** |
| Construction | P5041.1 building plans passed; P0151.1 materials prices; OECD `NODW`; ILO ISIC3 F | `NV.IND.TOTL.ZS` (2025, includes construction), `NE.GDI.FTOT.ZS` (2025) | Pink Sheet aluminium, copper | SA **good**, global **thin** |
| Manufacturing | P3041.2; P3043; P0142.1 PPI; OECD `PRVM`; ILO ISIC3 D | `NV.IND.MANF.ZS` (2025), `NV.IND.MANF.KD.ZG` (2025), `TX.VAL.MANF.ZS.UN` (2025), `TX.VAL.TECH.MF.ZS` (2024), GEM `IPTOTSAKD` | PMIs are proprietary (Unverified) | **Strong** |
| Technology | Report-based only | `IP.PAT.RESD` (2024), `GB.XPD.RSDV.GD.ZS` (2022), `BX.GSR.CCIS.CD` (2025), `TX.VAL.ICTG.ZS.UN` (2024), `IT.NET.SECR.P6` (2024), `SP.POP.SCIE.RD.P6` (2022) | OpenAlex research; startup funding is proprietary (Briter, TechCabal) | **Thin** |
| Finance and banking | BIS `WS_CBPOL`, `WS_TC`, `WS_DSR`; OECD `IR3TIB`, `IRLT`, `SHARE`; P0043.1; P0041; ILO ISIC3 J | `FR.INR.LEND` (2025), `FB.AST.NPER.ZS` (2024), `FS.AST.PRVT.GD.ZS` (2024), `FX.OWN.TOTL.ZS` (2024), `CM.MKT.LCAP.GD.ZS` (2025), `BX.KLT.DINV.WD.GD.ZS` (2025) | ECB FX; GEM `DSTKMKTXN`; SARB after consent | **Strong** but no live prices |
| Energy | P4141; Ember monthly; Eskom after consent | `EG.ELC.ACCS.ZS` (2024), `EG.ELC.COAL.ZS` (2024), `EG.USE.ELEC.KH.PC` (2024), `EG.ELC.LOSS.ZS` (2024) | EIA Brent, WTI, gas; Pink Sheet coal; `EN.GHG.CO2.MT.CE.AR5` (2024) | **Strong** |
| Logistics and transport | P7162; ILO ISIC3 I | `LP.LPI.OVRL.XQ` (2022), `IS.SHP.GOOD.TU` (2024), `IS.SHP.GCNW.XQ` (2021), `IS.AIR.GOOD.MT.K1` (2023), `IS.RRS.TOTL.KM` (2021), `TX.VAL.TRAN.ZS.WT` (2025) | UNCTAD maritime; EIA fuel; PortWatch not reliable yet | **Moderate** |
| Retail and commerce | P6242.1; P6141.2; OECD `TOVM`; GEM `RETSALESSA`; ILO ISIC3 G | `NE.CON.PRVT.ZS` (2025), `NE.CON.PRVT.KD.ZG` (2025), `FP.CPI.TOTL.ZG` (2025) | P0141 CPI detail | SA **strong**, Africa thin |
| Healthcare | No monthly series; P-files for mortality only | `SH.XPD.CHEX.GD.ZS` (2023), `SH.XPD.OOPC.CH.ZS` (2023), `SH.MED.PHYS.ZS` (2022), `SH.MED.NUMW.P3` (2022), `SP.DYN.LE00.IN` (2024) | WHO unusable directly; business data absent | **Thin** |
| Education | P9103.1 higher education finance, annual | `SE.XPD.TOTL.GD.ZS` (2024), `SE.TER.ENRR` (2023), `SE.TER.CUAT.BA.ZS` (2024), `SE.ADT.LITR.ZS` (2024) | No private-education market data | **Thin** |
| Real estate | P0160; P5041.1; BIS `WS_SPP` (2026-Q2); ILO ISIC3 K | No good WDI measure | BIS covers some markets; rents and commercial property are proprietary | SA **moderate**, Africa thin |
| Telecommunications | Report-75-01-01 stale at 2019 | `IT.CEL.SETS.P2` (2024), `IT.NET.USER.ZS` (2024), `IT.NET.BBND.P2` (2024) | Regulator and operator reports, manual. Annual only | **Thin** |
| Professional services | P0021 annual; ILO ISIC3 K, mixed with real estate | `BX.GSR.NFSV.CD` (2025), `BM.GSR.ROYL.CD` (2025), `NV.SRV.TOTL.ZS` (2025) | No direct measure anywhere | **Thinnest** |
| Hospitality and tourism | P6410; P6420; ILO ISIC3 H | `BX.GSR.TRVL.ZS` (2025); `ST.INT.ARVL` and `ST.INT.RCPT.CD` stale at 2020 | UN Tourism terms not read | SA **good**, global **stale** |
| Automotive | P6343.2; OECD `TOCAPA` | `IS.VEH.*` restricted and stale at 2010: do not use | Comtrade HS 87 trade; naamsa, OICA not checked | **Thin** |
| Renewable energy | Ember solar and wind; Eskom after consent | `EG.ELC.RNEW.ZS`, `EG.FEC.RNEW.ZS` stale at 2021 | Global Energy Monitor CC BY 4.0, form download, unclear commercial exemption note [search]; WDI IEA-origin data | **Moderate**, Ember carries it |

**Cross-cutting, SME** `IC.BUS.NREG` (2024), `IC.BUS.NDNS.ZS` (2024), P0043.1, P0041. Doing Business series are gone [called]. **Africa beyond SA:** expect annual World Bank data with a 1 to 2 year lag, IMF forecasts after permission, FX and commodity relevance, APO releases, GLEIF, and little else that updates. It will feel alive through trade, currencies and commodities, not local monthly statistics. National statistics offices are the next layer and need country-by-country terms checks.

**Thin overall:** professional services, healthcare, education, telecoms, automotive, technology. For those, lead with research, InfoDot business discovery and clearly labelled editorial, and show an honest coverage note rather than padding (brief section 14).

**IEA-origin label caveat.** WB labels IEA-origin series `CC BY-4.0` [called] while the IEA excludes its datasets from CC BY [read]. WB's terms say third-party restrictions appear in metadata [read], and none do here, so reliance is reasonable for free display, but I would not build a paid product on those series. Prefer Ember and EIA.

---

## 8. Architecture and governance implications (brief 14, 15)

1. **Licence is data.** A `source_licences` table: licence code, attribution template, public allowed, paid allowed, promotion-adjacent allowed, redistribution allowed, retention rule, permission document, review date. Every series and item links to it. Paid features filter on it server-side, enforcing brief section 12. Attribution renders from it, so it cannot be forgotten.
2. **Four connector types:** SDMX, REST-JSON, file (hash-based change detection, H-record and XLSX parsers), RSS/Atom. Sources are configuration.
3. **Series identity survives replacement.** Eurostat swapped a dataset, Ember replaced a file URL with a notice row, the Pink Sheet URL hash changes [called]. Treat a change in shape or a notice row as a health event, not a parse failure.
4. **Per-source rate policy in the queue:** GDELT 1 per 5 seconds, SEC 10 per second, Crossref 1 per second observed, EIA key limits. Never fetch from a page request.
5. **Freshness has two clocks:** reference period (what the number describes) and retrieved date. Staleness thresholds are per series frequency, not global.
6. **Conflict checks** across sources holding the same measure: policy rate (BIS vs SARB), Brent (EIA vs Pink Sheet), ZAR (ECB vs others), ZA unemployment (Stats SA vs ILO modelled).
7. **Keep modelled, forecast and observed apart.** ILO data contains projected years [called]. Store `obs_status` and source note.
8. **Summaries store lineage:** source item, content hash, model, prompt version, timestamp, reviewer.
9. **Contact in user agents:** use a project role mailbox, never a person's address.

---

## 9. Part B: landscape

Evidence note: this part is much thinner than Part A. I read two product pages directly. Most vendor facts are search summaries, often from sales-oriented sites. Analytical conclusions are my judgement and are marked Unverified.

| Platform | People come for | Organisation and presentation | Trust and return | Where users struggle |
|---|---|---|---|---|
| Bloomberg | Live data, messaging, news in one workspace (Unverified) | Terminal functions; news plus data | Seat near $32k a year per most aggregators, though sources conflict [search] | Cost; access limited to institutions [search] |
| Reuters | Speed, neutral wire (Unverified) | Wire and site; paywall expanding in some regions [search] | Agency reputation; no public RSS [search] | Paywall (Unverified) |
| Financial Times | Analysis, global business | Subscription, reported above 1.3m digital subscribers on a pricing site [search] | Editorial authority | Price, secondary source on numbers |
| CNBC | Market-day attention (Unverified) | Video and live coverage | Habit | Noise (Unverified) |
| Yahoo Finance | Quotes, watchlists (Unverified) | Ticker pages as the core unit | Watchlists and portfolio tools | Redistribution barred [search] |
| Trading Economics | Quick lookup of any country indicator | Country pages grouped as Overview, GDP, Labour, Prices, Money, Trade, Government, Business, Consumer, Housing; columns Last, Previous, Highest, Lowest, Unit, Date [read]. Calendar, Forecast, Markets [search] | Calendar drives return (Unverified); plan tiers split by data distribution rights [search] | No source per row on the overview page [read] |
| Statista | Citable charts fast | Search-first, one chart per page (Unverified) | Sourced graphics; paid from about $199 a month [search] | Paywalled results frustrate; pricing complaints on review sites [search] |
| S&P Global | Ratings, PMIs, Capital IQ data | Enterprise product; Koyfin's data partner [search] | Benchmarks | Price: Capital IQ Pro from about $1k per user per month [search] |
| McKinsey, Deloitte | Free thought leadership | Long reports, gated or not | Brand | Not checked [search returned nothing] |
| Gartner | Vendor evaluation, Magic Quadrant | Paid research, paid reprints; strict quoting rules [search] | Analyst authority | $50k+ cited on a review site; sharing limits [search] |
| Power BI, Tableau, Looker | Internal dashboards | Tools, not destinations | Enterprise adoption | Learning curve: DAX, Tableau polish, LookML [search] |
| Similarweb | Website traffic estimates | Domain pages | Competitive data | Thin free plan; real use starts around $14k a year per one review [search] |
| Crunchbase | Company and funding lookups | Company profile | Profile pages as SEO | Free tier unclear, API free tier reportedly removed; Pro $49 to $99 a month [search] |
| CB Insights | Market maps, private company data | Sales-led | Research brand | No public pricing [search] |
| Our World in Data | Understanding long-run trends | Data page: chart, what you should know, metadata (unit, source, last and next update, range), related charts, FAQ, sources and processing, reuse, citations, download with code [read] | Radical transparency; CC BY; donations | Not a news product (Unverified) |
| Morning Brew | Fast daily catch-up | Newsletter, moving beyond email: over 60% of engagement elsewhere [search] | Tone and habit; advertising-led, B2B brands growing [search] | Depth (Unverified) |
| Semafor | Global news with structure | "Semaform" separates news, reporter's view, room for disagreement, view from elsewhere [search]. Africa newsletter three times a week [read] | Clear separation of fact and view | Critics find the format confusing [search title only] |
| Stratechery | One voice, frameworks | Single analyst, subscription [search] | Consistent analysis | One-person limit (Unverified) |
| Rest of World | Tech outside the West | Free, nonprofit, foundation-funded [search]; reported standalone end January 2027 [search, one source] | Reporting | Shows how fragile this niche funding is |
| TechCabal Insights | African tech numbers | State of Tech in Africa reports, proprietary database, H1 2026 edition, email gate apparent [search] | Africa focus | Gated |
| Briter | African investment data | Reports plus paid platform (Unverified). Totals include estimates for undisclosed deals; methods changed between years [search] | Clear on method | Cross-tracker totals vary by definition [search] |
| Business Day | SA business news | Subscribe prompts seen; paywall unconfirmed [search] | Heritage | Not checked |
| Koyfin | Affordable terminal | S&P data, freemium, Plus around $39 to $49 [search] | Value | Delayed data on free [search] |
| Tegus | Expert transcripts | Not checked | n/a | Search returned nothing |
| Perplexity Finance | Quick cited answers | Licensed data via 14+ providers, Quartr for earnings [search]; page did not render for me [called] | Clickable numbers [search]; vendor accuracy claims not independent [search] | Reviews cite limited depth [search] |
| Datawrapper | Publishing clean charts | Title, description, notes, source, alt text; mobile annotations become a key [search] | Newsroom standard | Tool, not destination |
| FlowingData | Learning to read data | Author-led; advises annotating and highlighting [search] | Expertise | Partly members-only [search] |

### Patterns (analysis, Unverified unless tagged)
- **Daily habit comes from a calendar or a newsletter**, not from dashboards. Morning Brew and Trading Economics show this from different angles.
- **The unit of the web is a page per thing**: ticker, company, country, indicator. Crunchbase, Yahoo and Trading Economics rank because each entity has a stable URL.
- **Trust is visible method.** OWID's page anatomy [read] is the best free template. Trading Economics' weakness is exactly the missing source per row [read].
- **Structured separation works.** Semafor's format matches the brief's demand to separate fact, interpretation and forecast.
- **Paywalls after the search result make people angry.** Statista complaints [search] show what not to do.
- **BI tools are powerful but hard**, and none is a destination. A built-in question is better than a builder.

### Gaps
1. No free, sourced, Africa-aware equivalent of Trading Economics with source per row and a clear licence.
2. South African sector statistics exist but sit in ZIP files; nobody free presents them well as a story. Unverified as a market claim.
3. Africa-wide funding and company data is paid, gated or inconsistent [search].
4. A small business has no plain answer to "what should I watch this month?"
5. Nothing connects a macro signal to a real supplier one can contact.

### What only a free, well-sourced, Africa-aware product with InfoDot can do
- **Go from number to business.** Mining production falls, here is the data, here is what it may mean, here are verified mining suppliers on InfoDot. No terminal does that.
- **Coverage honesty as a feature.** "We have no monthly automotive data for Kenya, and here is why."
- **Plain language for owners, not traders.**
- **Compare SA provinces and sectors** using P0441.2, Stats SA and InfoDot's own aggregated directory.
- **Own data.** InfoDot's anonymised, aggregated, consent-respecting activity (new businesses by sector, questions asked) is the one dataset nobody can license elsewhere. Needs POPIA review.

---

## 10. Product decisions

1. **Launch a primary-source newsroom, not a news aggregator.** Show only sources with rights. Add publishers by licence.
2. **Make South Africa the proof.** Launch the 17 industries for SA first on Stats SA and OECD, then extend to all countries with World Bank data, then deepen Africa country by country.
3. **Adopt one page anatomy for every indicator**, modelled on OWID's sections but ours: the number, the plain-language meaning, unit, period, source, last and next update, licence, limits, download, related businesses. Do not copy their interface.
4. **Build stable pages per entity** with clean URLs: country, industry, country-plus-industry, indicator, company, source. SEO and shareable. This is the growth engine.
5. **Make a release calendar the daily habit.** Stats SA, SARB (dates only, once cleared), Fed, ECB, Treasury. Plus a short morning briefing by email through Dot.Notify.
6. **Show a coverage grade on every industry and country page**, including "thin", per section 7.
7. **Licence-aware by construction.** The registry in section 8 drives what can be shown, exported or sold. The API and paid tier can never include FAO, WHO, IEA, FRED or uncleared items.
8. **Enforce four labels on every item:** Source fact, Calculated by us, AI summary, Editorial view. Forecasts are a fifth, with assumptions. This meets brief sections 8 and 14 and borrows Semafor's discipline.
9. **Charge for work, not facts.** Keep public data free forever. Paid value is alerts, custom watchlists, comparisons, workspaces, InfoDot-derived data and analyst reports. Licensed market data only arrives with a paid licence.
10. **Do the permission letters before the design.** SARB, GCIS, World Bank (Pink Sheet) and IMF decide what the homepage can show. Design with them as optional modules so the page stands without them.

---

## 11. Not verified, and why

- **ILOSTAT, UNIDO, AfDB terms:** challenge pages blocked me; I did not try to pass them.
- **Reuters, AP, Business Wire, GlobeNewswire, TechCabal, BusinessTech terms:** blocked or not found.
- **Pink Sheet licence:** not found on the World Bank page.
- **Crossref and OpenAlex licences:** pages not retrieved; well-known but unconfirmed today.
- **UN Comtrade current redistribution policy:** only older sources found.
- **Case law:** secondary reports only.
- **Part B:** mostly search summaries, several from sales-oriented sites. Bloomberg's price is disputed across sources. No user research was done.
- **History depth and rate limits** are unknown where marked.

**Reproducing tests:** every [called] URL above can be run with plain curl. Add `-g` for EIA and for bracketed GLEIF filters.
