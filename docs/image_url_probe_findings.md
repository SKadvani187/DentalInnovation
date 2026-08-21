# Product image URL probe — findings

**Conclusion: NO FIX NEEDED. Do not alter the image URLs in the DB or smartdental_import.json.**

The 289 URLs that *look* malformed (e.g. `...image.jpgv1750244350width1946`) are NOT
broken. The suffix is part of the actual CloudFront/S3 object key, not a stray query
string. Every one of them was HTTP-probed and returns a real image.

## Probe results (HTTP, range request; 206/200 = loads)
- URLs where the original works and the "cleaned" version 404/403s: 265
  (=> the original URL is correct; cleaning it would BREAK the image)
- URLs where both original and cleaned work: 24
- URLs actually broken: 0

Verified samples returned Content-Type: image/jpeg, sizes 34KB-457KB.

Probed on 2026-06-20.
