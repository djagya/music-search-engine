import React, {useEffect, useState} from 'react';
import axios from 'axios';
import styles from './InstanceStatus.module.scss';
import {cx} from '../ui';

export default function InstanceStatus() {
  const [isRunning, setStatus] = useState<boolean>(false);
  const [isLoading, setLoading] = useState<boolean>(false);

  useEffect(() => {
    setLoading(true);
    axios
      .get('/instance')
      .then(res => {
        setStatus(res.data.running === true);
        setLoading(false);
      })
      .catch(e => {
        console.log(e);
        setLoading(false);
      });
  }, []);

  function handleChange() {
    setLoading(true);
    axios
      .post('/instance', {start: !isRunning})
      .then(res => {
        setStatus(res.data.running === true);
        setLoading(false);
      })
      .catch(e => {
        console.log(e);
        setLoading(false);
      });
  }

  return (
    <div className={cx(styles.container, isRunning && styles.active, isLoading && styles.loading)}>
      <label className={styles.switch}>
        <input type="checkbox" checked={isRunning} onChange={handleChange} />
        <span className={styles.switcher} />
      </label>

      <small className={styles.info}>{isLoading ? 'loading' : isRunning ? 'running' : 'stopped'}</small>
    </div>
  );
}
