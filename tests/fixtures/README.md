# Immutable upgrade input

`VoilaVoila-CMS-1.2.1.zip` is the previously delivered 1.2.1 application archive, SHA-256 `cf961383ebc1d10628240076c7aeee36b692ef9a46f79a68fa2d01b8d5178d4d`. It contains distribution source, without runtime storage. It supports repeatable upgrade/rollback checks. Do not edit it.

Real WordPress ZIPs, verification bundles, databases and installed engines remain outside this repository and release. Use `tools/fetch_wordpress_fixture.py` to prepare fresh official test inputs.

`VoilaVoila-CMS-1.3.0.zip` is the immutable delivered 1.3.0 source package, SHA-256 `1bf773b9001c6c32d3826edf3459df51f2b644807d48f4dd3cebfeda34e0c6e6`, used for the authenticator integration upgrade/rollback test.
