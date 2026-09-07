<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Why a token was not accepted. Publishers can ignore this and just branch on `subscriber`, but it is
 * worth logging: a burst of `WRONG_HOSTNAME` means somebody is replaying tokens harvested elsewhere,
 * and any `FORGED` at all means somebody is minting them.
 */
class Rejection
{
    /** No token header on the request. The visitor has no extension, or is not a subscriber. */
    public const MISSING = "missing";

    /** Not a well-formed token: wrong length, not base64url, or a plan byte nobody recognises. */
    public const MALFORMED = "malformed";

    /** A token format this SDK version predates. Upgrade the package. */
    public const UNSUPPORTED_VERSION = "unsupported_version";

    /** Structurally sound, correctly signed, but past its expiry. */
    public const EXPIRED = "expired";

    /** The hostname given to `verify()` is not one this publisher was configured to serve. */
    public const UNKNOWN_HOSTNAME = "unknown_hostname";

    /** Genuine token, but bound to a different site. Somebody tried to replay another site's traffic. */
    public const WRONG_HOSTNAME = "wrong_hostname";

    /** The authority signature does not check out. The token was not issued by Zero Ad Network. */
    public const FORGED = "forged";
}
