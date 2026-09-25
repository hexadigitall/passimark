import { Component } from 'react';

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, message: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, message: error?.message ?? String(error) };
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="passimark-app__fault">
          <p className="passimark-app__fault-eyebrow">Passimark recovered the render</p>
          <h1 className="passimark-app__fault-title">This screen hit a runtime fault - it is never a blank page</h1>
          <p className="passimark-app__fault-body">{this.state.message}</p>
          <p className="passimark-app__fault-hint">
            The fault is named here on purpose so it can be fixed at its source - reload to try the rung again.
          </p>
          <a className="passimark-app__fault-reload" href={window.location.pathname}>
            Reload this rung
          </a>
        </div>
      );
    }
    return this.props.children;
  }
}
