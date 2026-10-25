# Changelog

## 2.1.0 â€” 2026-10-25

- Adds module-activated Core commerce: products, inventory-aware carts,
  checkout, immutable order-item snapshots, and an administrator order
  workflow. Store routes and navigation remain absent unless an enabled
  module declares the relevant feature.
- Adds manual Venmo, PayPal, and Cash App checkout methods with administrator
  on/off controls and customer instructions. Manual orders remain payment
  pending until staff records receipt; no gateway confirmation is implied.
- Adds optional public customer accounts with registration, sign-in, profile,
  password recovery, order history, rate limiting, and Core customer
  administration. Modules that do not opt in expose none of these surfaces.
- Adds package ownership to commerce/customer records. Disable and uninstall
  preserve data; explicit package purge removes the package partition from
  Core-owned feature tables.
- Adds additive migration `2026_10_25_0029`, Core/module feature declarations,
  conditional permissions/navigation/search/dashboard integration, audit
  records, transactional order notices, and `commerce.order.placed` webhooks.

## 2.0.32 â€” 2026-10-25

- Adds Core-owned, first-party analytics across Core pages and every industry
  module without requiring Google Analytics or module-specific tracking code.
- Adds an owner-only Analytics workspace with traffic trends, live visitors,
  visitor journeys, acquisition, device/browser/OS, engagement, geography,
 6ß{îÚ$z{-®éÜj×™Ù]
	Ù]X˜\ÙK›˜[YIÊK	ÛÛ™šYËO™Ù]
	Ù]X˜\ÙKœ™Yš^	ÊJNÂŠ™]ÈÛÜ™W]X˜\ÙWZYÜ˜]ÜŠ	‹	××Ô“ÓÕ×ËÙ]X˜\ÙKÛZYÜ˜][ÛœÉÊJKO›ZYÜ˜]J
NÂ‰‹Oš[œÙ\
	ØYZ[œÉËÂˆ	İ\Ù\›˜[YIÈOˆ	ÛİÛ™\‰Ë	Ù[XZ[	ÈOˆ	ÛİÛ™\™\šYK›ØØ[	Ëˆ	Ü\ÜİÛÜ™Ú\Ú	ÈOˆ\ÜİÛÜ™Ú\Ú
	İ™\šYK\\ÜËLIËTÔÕÓÔ‘ÑQUS
Kˆ	Ü›ÛIÈOˆ	ÛİÛ™\‰Ë	ÛY˜WÙ[˜X›Y	ÈOˆ	Ù\ØX›Y	ÈOˆˆ	ØÜ™X]YØ]	ÈOˆ]J	ÖK[KYšNœÉÊK—JNÂ‰‹Oš[œÙ\
	ÜYÙ\ÉËÂˆ	ÜÛYÉÈOˆ	ÚÛYIË	İ]IÈOˆ	Õ™\šYšXØ][ÛˆÛYIËˆ	Ø›ÙIÈOˆ	Ï\Y˜Xİ™\šYšXØ][ÛˆYÙH8 %H^˜XİYXÚØYÙH›ÛİËÜ‰Ëˆ	Üİ]\ÉÈOˆ	ÜX›\ÚY	Ë	ØÜ™X]YØ]	ÈOˆ]J	ÖK[KYšNœÉÊKˆ	İ\]YØ]	ÈOˆ]J	ÖK[KYšNœÉÊK—JNÂ‰Ù][™ÜÈH™]ÈÛÜ™WÙ][™Ü×Ù][™ÜÔ™\ÜÚ]ÜJ	ŠNÂ‰Ù][™ÜËOœÙ]
	ÜÚ]K›˜[YIË	Ğ\Y˜Xİ™\šYšXØ][ÛˆÚ]IÊNÂ‰Ù][™ÜËOœÙ]
	ÜÚ]KšÛYWÜÛYÉË	ÚÛYIÊNÂ‰Ù][™ÜËOœÙ]
	ÜŞ\İ[K˜Ü›Û—İÚÙ[‰Ëš[Œš^
˜[™ÛWØ]\Ê
JJNÂ™XÚÈ›ZYÜ˜]Y
ÈÙYYYˆÂ”Â‰ØÜš\HİŠ	ØÜš\É××Ô“ÓÕ×ÉÈOˆ	İ]\—JNÂ‰ÙYYš[HH	İ]\ˆˆ	ËÜİÜ˜YÙKİ™\šYK\ÙYYœ	ÎÂ™š[WÜ]ØÛÛ[Ê	ÙYYš[K	ØÜš\
NÂ‰İ]H×NÂ™^XÊ	È‰ÈˆĞ’ST–Hˆ	Èˆ	Èˆ\ØØ\\Ú[\™Ê	ÙYYš[JHˆ	È‰ŒIË	İ]	ÛÙJNÂ™XÚÈ[\ÙJ—ˆ‹	İ]
Hˆ—ˆÂšYˆ
	ÛÙHOOH\İ—ØÛÛZ[œÊ[\ÙJ—ˆ‹	İ]
K	ÛZYÜ˜]Y
ÈÙYYY	ÊJHÂˆÜš]JÕT”‹›ZYÜ˜]KÜÙYY˜Z[YˆŠNÂˆ^]
JNÂŸB‚™XÚÈ—‘ĞÔ“ÓÕ^Éİ]\ŸWˆÂ™XÚÈœÙ\™NˆTÈLËŒŒŒNÉÜH]Éİ]\ŸWˆÛÛËÙ]‹\›İ]\‹œˆÂ™XÚÈ›ÙÚ[ˆİÛ™\ˆÈ™\šYK\\ÜËLH]É˜\ÙU\›KØYZ[—ˆÂ