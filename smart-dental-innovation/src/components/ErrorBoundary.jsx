import { Component } from "react";

// Top-level error boundary. A render/lifecycle throw anywhere below would otherwise
// unmount the whole React tree and leave a blank white page. This catches it and shows
// a recoverable fallback (reload / go home) instead.
export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error, info) {
    // Surface to the console for debugging; wire to a real logger/Sentry later if desired.
    console.error("[ErrorBoundary]", error, info?.componentStack);
  }

  handleReload = () => {
    this.setState({ hasError: false });
    window.location.reload();
  };

  handleHome = () => {
    this.setState({ hasError: false });
    window.location.assign("/");
  };

  render() {
    if (!this.state.hasError) return this.props.children;
    return (
      <div
        role="alert"
        style={{
          minHeight: "60vh",
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          justifyContent: "center",
          textAlign: "center",
          padding: "2rem",
          fontFamily: "system-ui, sans-serif",
        }}
      >
        <div style={{ fontSize: "3rem", marginBottom: "0.5rem" }}>⚠️</div>
        <h1 style={{ fontSize: "1.4rem", fontWeight: 700, color: "#0b2545", margin: "0 0 0.5rem" }}>
          Something went wrong
        </h1>
        <p style={{ color: "#6b7280", maxWidth: 420, margin: "0 0 1.5rem" }}>
          The page hit an unexpected error. You can reload or head back to the home page.
        </p>
        <div style={{ display: "flex", gap: "0.75rem" }}>
          <button
            onClick={this.handleReload}
            style={{ background: "#3684bf", color: "#fff", fontWeight: 700, border: "none", borderRadius: 10, padding: "0.7rem 1.4rem", cursor: "pointer" }}
          >
            Reload
          </button>
          <button
            onClick={this.handleHome}
            style={{ background: "#fff", color: "#0b2545", fontWeight: 700, border: "1px solid #d1d5db", borderRadius: 10, padding: "0.7rem 1.4rem", cursor: "pointer" }}
          >
            Go Home
          </button>
        </div>
      </div>
    );
  }
}
