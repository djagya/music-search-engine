import React from 'react';

export default class ErrorBoundary extends React.Component {
  state = {
    hasError: false,
  };

  static getDerivedStateFromError(error: any) {
    console.log(error);
    return { hasError: true };
  }

  render() {
    if (this.state.hasError) {
      return <span style={{ color: '#f44336' }}>Something went wrong.</span>;
    }

    return this.props.children;
  }
}
