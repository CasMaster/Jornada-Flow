import hashlib
import hmac
import importlib.util
from pathlib import Path
import unittest


SCRIPT = Path(__file__).resolve().parents[1] / "mixhome-verify-web-approval.py"
spec = importlib.util.spec_from_file_location("mixhome_web_approval", SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class WebApprovalTest(unittest.TestCase):
    def test_signature_is_bound_to_environment_digest_actor_and_expiry(self):
        key = b"a" * 32
        digest = "b" * 64
        expires = 1300
        actor = 42
        signature = hmac.new(
            key, f"producao:{digest}:{expires}:{actor}".encode(), hashlib.sha256
        ).hexdigest()
        approval = {"expires": expires, "actor": actor, "signature": signature}

        self.assertTrue(module.valid_approval(key, approval, "producao", digest, 1000))
        self.assertFalse(module.valid_approval(key, approval, "homologacao", digest, 1000))
        self.assertFalse(module.valid_approval(key, approval, "producao", "c" * 64, 1000))
        self.assertFalse(module.valid_approval(b"z" * 32, approval, "producao", digest, 1000))
        self.assertFalse(module.valid_approval(key, approval, "producao", digest, 1301))
        self.assertFalse(module.valid_approval(key, approval, "producao", digest, 399))
        self.assertFalse(module.valid_approval(key, {**approval, "actor": 43}, "producao", digest, 1000))


if __name__ == "__main__":
    unittest.main()
