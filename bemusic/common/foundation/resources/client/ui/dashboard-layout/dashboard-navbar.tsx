import {IconButton} from '@ui/buttons/icon-button';
import {MenuOpenIcon} from '@ui/icons/material/MenuOpen';
import clsx from 'clsx';
import {useContext} from 'react';
import {Navbar, NavbarProps} from '../navigation/navbar/navbar';
import {DashboardLayoutContext} from './dashboard-layout-context';

export interface DashboardNavbarProps extends Omit<
  NavbarProps,
  'toggleButton'
> {
  hideToggleButton?: boolean;
}
export function DashboardNavbar({
  children,
  className,
  hideToggleButton,
  ...props
}: DashboardNavbarProps) {
  const {
    isMobileMode,
    leftSidenavCanBeCompact,
    toggleLeftSidenavStatus,
    toggleLeftSidenavCompactMode,
  } = useContext(DashboardLayoutContext);

  const shouldToggleCompactMode = leftSidenavCanBeCompact && !isMobileMode;
  const shouldShowToggle =
    !hideToggleButton && (isMobileMode || leftSidenavCanBeCompact);

  return (
    <Navbar
      className={clsx('dashboard-grid-navbar', className)}
      border="border-b"
      size="sm"
      toggleButton={
        shouldShowToggle ? (
          <IconButton
            size="md"
            onClick={() => {
              if (shouldToggleCompactMode) {
                toggleLeftSidenavCompactMode();
              } else {
                toggleLeftSidenavStatus();
              }
            }}
          >
            <MenuOpenIcon />
          </IconButton>
        ) : undefined
      }
      {...props}
    >
      {children}
    </Navbar>
  );
}
