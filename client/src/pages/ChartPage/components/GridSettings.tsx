import styles from '../Chart.module.scss';
import React from 'react';

interface OptionSelectProps {
  name: string;
  items: { [value: string]: string };
  active: string;
  onChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  children: string;
}

export function OptionSelect({ name, items, active, onChange, children }: OptionSelectProps) {
  return (
    <div className={styles.OptionSelect}>
      <b>{children}</b>&nbsp;
      <ul>
        {Object.keys(items).map(key => (
          <li>
            <label>
              <input type="radio" name={name} value={key} checked={key === active} onChange={onChange} />
              &nbsp;
              {items[key]}
            </label>
          </li>
        ))}
      </ul>
    </div>
  );
}

function RadioItem({
  name,
  value,
  checked,
  onChange,
  children,
}: {
  name: string;
  value: string;
  checked: boolean;
  onChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  children: any;
}) {
  return (
    <li>
      <label>
        <input type="radio" name={name} value={value} checked={checked} onChange={onChange} />
        &nbsp;
        {children}
      </label>
    </li>
  );
}

export function ChartModeSwitch({ chartMode, onChange }: { chartMode: boolean; onChange: any }) {
  return (
    <div>
      <label>
        <b>Chart?</b> <input type="checkbox" name="chartMode" checked={chartMode} onChange={onChange} />
      </label>
    </div>
  );
}
