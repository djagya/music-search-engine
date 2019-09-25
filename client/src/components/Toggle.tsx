import React from 'react';
import styles from './InstanceStatus.module.scss';
import {cx} from '../ui';

export default function Toggle({
                                   isActive,
                                   isLoading = false,
                                   labels = {on: 'on', loading: 'loading', off: 'off'},
                                   onChange,
                               }: {
    isActive: boolean;
    isLoading?: boolean;
    labels: { on: string; loading?: string; off: string };
    onChange: { (v: boolean): void };
}) {
    return (
        <div className={cx(styles.container, isActive && styles.active, isLoading && styles.loading)}>
            <label className={styles.switch}>
                <input type="checkbox" checked={isActive} onChange={() => onChange(!isActive)}/>
                <span className={styles.switcher}/>
            </label>

            <small className={styles.info}>{isLoading ? labels.loading : isActive ? labels.on : labels.off}</small>
        </div>
    );
}
