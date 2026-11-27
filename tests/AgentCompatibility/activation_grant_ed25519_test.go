package compatibility

import (
    "bytes"
    "crypto/ed25519"
    "encoding/base64"
    "encoding/json"
    "io"
    "os"
    "testing"
)

type activationGrantVector struct {
    PrivateSeedBase64 string          `json:"private_seed_base64"`
    PublicKeyBase64   string          `json:"public_key_base64"`
    SignatureBase64   string          `json:"signature_base64"`
    CanonicalJSON     string          `json:"canonical_json"`
    Payload           json.RawMessage `json:"payload"`
}

func TestCentralActivationGrantMatchesAgentEd25519Verifier(t *testing.T) {
    fixtureBytes, err := os.ReadFile("../fixtures/activation-grant-ed25519-vector.json")
    if err != nil {
        t.Fatalf("read compatibility fixture: %v", err)
    }

    var fixture activationGrantVector
    if err := json.Unmarshal(fixtureBytes, &fixture); err != nil {
        t.Fatalf("decode compatibility fixture: %v", err)
    }

    seed, err := base64.StdEncoding.DecodeString(fixture.PrivateSeedBase64)
    if err != nil {
        t.Fatalf("decode test-only private seed: %v", err)
    }
    if len(seed) != ed25519.SeedSize {
        t.Fatalf("unexpected Ed25519 seed length: %d", len(seed))
    }

    privateKey := ed25519.NewKeyFromSeed(seed)
    publicKey := privateKey.Public().(ed25519.PublicKey)

    expectedPublicKey, err := base64.StdEncoding.DecodeString(fixture.PublicKeyBase64)
    if err != nil {
        t.Fatalf("decode public key: %v", err)
    }
    if !bytes.Equal(publicKey, expectedPublicKey) {
        t.Fatal("test vector public key does not match its seed")
    }

    canonical, err := agentCanonicalJSON(fixture.Payload)
    if err != nil {
        t.Fatalf("canonicalize Agent payload: %v", err)
    }
    if string(canonical) != fixture.CanonicalJSON {
        t.Fatalf("canonical JSON mismatch\nexpected: %s\nactual:   %s", fixture.CanonicalJSON, canonical)
    }

    signature, err := base64.StdEncoding.DecodeString(fixture.SignatureBase64)
    if err != nil {
        t.Fatalf("decode signature: %v", err)
    }
    if !ed25519.Verify(publicKey, canonical, signature) {
        t.Fatal("Agent Ed25519 verification rejected Central signature")
    }

    generatedSignature := ed25519.Sign(privateKey, canonical)
    if !bytes.Equal(generatedSignature, signature) {
        t.Fatal("Central and Agent Ed25519 signature bytes differ")
    }
}

func agentCanonicalJSON(payload []byte) ([]byte, error) {
    decoder := json.NewDecoder(bytes.NewReader(payload))
    decoder.UseNumber()

    var value any
    if err := decoder.Decode(&value); err != nil {
        return nil, err
    }

    var extra any
    if err := decoder.Decode(&extra); err != io.EOF {
        if err == nil {
            return nil, io.ErrUnexpectedEOF
        }
        return nil, err
    }

    var buffer bytes.Buffer
    encoder := json.NewEncoder(&buffer)
    encoder.SetEscapeHTML(false)

    if err := encoder.Encode(value); err != nil {
        return nil, err
    }

    return bytes.TrimSuffix(buffer.Bytes(), []byte("\n")), nil
}

