import React, {useEffect, useState} from 'react';
import axios from 'axios';
import Toggle from './Toggle';

/**
 * AWS instance switch.
 */
export default function InstanceStatus({ onChange }: { onChange: { (v: boolean): void } }) {
  const [isRunning, setStatus] = useState<boolean>(false);
  const [isLoading, setLoading] = useState<boolean>(false);

  function getStatus() {
    axios
      .get('/api/instance')
      .then(res => {
        setStatus(res.data.running === true);
        setLoading(false);
      })
      .catch(e => {
        console.log(e);
        setLoading(false);
      });
  }

  useEffect(() => {
    setLoading(true);
    getStatus();
  }, []);

  useEffect(() => {
    onChange(isRunning);
  }, [isRunning, onChange]);

  function handleChange() {
    setLoading(true);
    axios
      .post('/api/instance', { start: !isRunning })
      .then(res => {
        // Hacky way to avoid dealing with AWS api responses.
        setStatus(!isRunning);
        setLoading(true);
        setTimeout(() => {
          getStatus();
        }, 5000);
      })
      .catch(e => {
        console.log(e);
        setLoading(false);
      });
  }

  return (
      <Toggle
          isActive={isRunning}
          isLoading={isLoading}
          labels={{on: 'running', off: 'stopped', loading: 'loading'}}
          onChange={handleChange}
      />
  );
}
